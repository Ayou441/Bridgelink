<?php
session_start();
header('Content-Type: application/json');
include '../db.php';

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if(!isset($data['type'])){
    echo json_encode(['success' => false, 'error' => 'Type manquant']);
    exit();
}

switch($data['type']){
    case 'album':
        // Créer un album
        $album_name = $data['album_name'];
        
        // Vérifier si l'album existe déjà
        $check = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND album_name = ?");
        $check->bind_param("is", $user_id, $album_name);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows > 0){
            echo json_encode(['success' => false, 'error' => 'Album déjà existant']);
        } else {
            $stmt = $conn->prepare("INSERT INTO albums (user_id, album_name) VALUES (?, ?)");
            $stmt->bind_param("is", $user_id, $album_name);
            if($stmt->execute()){
                echo json_encode(['success' => true, 'album_id' => $conn->insert_id]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur création: ' . $conn->error]);
            }
        }
        break;
        
    case 'delete_album':
        // Supprimer un album
        $album_name = $data['album_name'];
        $stmt = $conn->prepare("SELECT id FROM albums WHERE user_id = ? AND album_name = ?");
        $stmt->bind_param("is", $user_id, $album_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($album = $result->fetch_assoc()){
            $album_id = $album['id'];
            $stmt = $conn->prepare("DELETE FROM albums WHERE id = ?");
            $stmt->bind_param("i", $album_id);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Album non trouvé']);
        }
        break;
        
    case 'rename_album':
        // Renommer un album
        $old_name = $data['old_name'];
        $new_name = $data['new_name'];
        $stmt = $conn->prepare("UPDATE albums SET album_name = ? WHERE user_id = ? AND album_name = ?");
        $stmt->bind_param("sis", $new_name, $user_id, $old_name);
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur renommage: ' . $conn->error]);
        }
        break;
        
    case 'photo':
    // Ajouter une photo (dans son album OU dans l'album d'un compte lié)
    $album_name = $data['album_name'];
    $photo_name = $data['photo_name'];
    $photo_data = $data['photo_data'];
    
    // Extraire le vrai nom de l'album si c'est un album partagé "Album (de user)"
    $real_album_name = $album_name;
    if(preg_match('/^(.+) \(de .+\)$/', $album_name, $matches)){
        $real_album_name = $matches[1];
    }
    
    // Récupérer les IDs des utilisateurs liés
    $linked_ids = [$user_id];
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN parent_id = ? THEN child_id
                WHEN child_id = ? THEN parent_id
            END as linked_id
        FROM family_links
        WHERE parent_id = ? OR child_id = ?
    ");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $linked_ids[] = $row['linked_id'];
    }
    
    // Chercher l'album parmi TOUS les comptes liés
    $placeholders = implode(',', array_fill(0, count($linked_ids), '?'));
    $stmt = $conn->prepare("SELECT id, user_id FROM albums WHERE user_id IN ($placeholders) AND album_name = ?");
    $types = str_repeat('i', count($linked_ids)) . 's';
    $params = array_merge($linked_ids, [$real_album_name]);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($album = $result->fetch_assoc()){
        $album_id = $album['id'];
        
        // Vérifier la taille de la photo (max 5MB en base64)
        if(strlen($photo_data) > 7000000) {
            echo json_encode(['success' => false, 'error' => 'Photo trop volumineuse (max 5MB)']);
            break;
        }
        
        $stmt = $conn->prepare("INSERT INTO album_photos (album_id, photo_name, photo_data) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $album_id, $photo_name, $photo_data);
        if($stmt->execute()){
            echo json_encode(['success' => true, 'photo_id' => $conn->insert_id]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur ajout photo: ' . $conn->error]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Album non trouvé']);
    }
    break;
        
    case 'delete_photo':
    // Supprimer une photo (dans son album OU dans l'album d'un compte lié)
    $album_name = $data['album_name'];
    $photo_name = $data['photo_name'];
    
    // Extraire le vrai nom de l'album si c'est un album partagé
    $real_album_name = $album_name;
    if(preg_match('/^(.+) \(de .+\)$/', $album_name, $matches)){
        $real_album_name = $matches[1];
    }
    
    // Récupérer les IDs des utilisateurs liés
    $linked_ids = [$user_id];
    $stmt = $conn->prepare("
        SELECT 
            CASE 
                WHEN parent_id = ? THEN child_id
                WHEN child_id = ? THEN parent_id
            END as linked_id
        FROM family_links
        WHERE parent_id = ? OR child_id = ?
    ");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while($row = $result->fetch_assoc()){
        $linked_ids[] = $row['linked_id'];
    }
    
    // Supprimer parmi TOUS les albums des comptes liés
    $placeholders = implode(',', array_fill(0, count($linked_ids), '?'));
    $stmt = $conn->prepare("
        DELETE ap FROM album_photos ap
        INNER JOIN albums a ON ap.album_id = a.id
        WHERE a.user_id IN ($placeholders) AND a.album_name = ? AND ap.photo_name = ?
    ");
    $types = str_repeat('i', count($linked_ids)) . 'ss';
    $params = array_merge($linked_ids, [$real_album_name, $photo_name]);
    $stmt->bind_param($types, ...$params);
    
    if($stmt->execute()){
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Erreur suppression: ' . $conn->error]);
    }
    break;
        
    case 'quiz':
        // Sauvegarder réponses quiz
        $theme = $data['theme'];
        $role = $data['role'];
        $answers = json_encode($data['answers']);
        
        $stmt = $conn->prepare("REPLACE INTO quiz_answers (user_id, theme, role, answers) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $theme, $role, $answers);
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur sauvegarde quiz: ' . $conn->error]);
        }
        break;
        
    case 'delete_quiz':
        // Supprimer les réponses quiz d'un thème
        $theme = $data['theme'];
        $stmt = $conn->prepare("DELETE FROM quiz_answers WHERE user_id = ? AND theme = ?");
        $stmt->bind_param("is", $user_id, $theme);
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur suppression: ' . $conn->error]);
        }
        break;
        
    case 'game_score':
        // Sauvegarder score de jeu
        $game_name = $data['game_name'];
        $score_data = json_encode($data['score_data']);
        
        $stmt = $conn->prepare("REPLACE INTO game_scores (user_id, game_name, score_data) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $game_name, $score_data);
        if($stmt->execute()){
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur sauvegarde score: ' . $conn->error]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Type inconnu: ' . $data['type']]);
}
?>