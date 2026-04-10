<?php
session_start();
header('Content-Type: application/json');
include '../db.php';

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';

// Fonction pour obtenir les IDs des utilisateurs liés
function getLinkedUserIds($conn, $user_id) {
    $linked_ids = [$user_id]; // Inclure l'utilisateur actuel
    
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
    
    return $linked_ids;
}

$linked_ids = getLinkedUserIds($conn, $user_id);

switch($type){
    case 'albums':
        // Charger TOUS les albums des comptes liés
        $all_albums = [];
        
        foreach($linked_ids as $id){
            $stmt = $conn->prepare("SELECT id, album_name, user_id FROM albums WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while($album = $result->fetch_assoc()){
                $album_id = $album['id'];
                $album_name = $album['album_name'];
                $owner_id = $album['user_id'];
                
                // Charger les photos de cet album
                $photos_stmt = $conn->prepare("SELECT photo_name, photo_data FROM album_photos WHERE album_id = ? ORDER BY created_at");
                $photos_stmt->bind_param("i", $album_id);
                $photos_stmt->execute();
                $photos_result = $photos_stmt->get_result();
                
                $photos = [];
                while($photo = $photos_result->fetch_assoc()){
                    $photos[] = [
                        'name' => $photo['photo_name'],
                        'data' => $photo['photo_data']
                    ];
                }
                
                // Ajouter l'info du propriétaire
                $display_name = $album_name;
                if($owner_id != $user_id){
                    // Récupérer le nom du propriétaire
                    $owner_stmt = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
                    $owner_stmt->bind_param("i", $owner_id);
                    $owner_stmt->execute();
                    $owner_result = $owner_stmt->get_result();
                    $owner = $owner_result->fetch_assoc();
                    $display_name = $album_name;
                }
                
                $all_albums[$display_name] = [
                    'photos' => $photos,
                    'owner_id' => $owner_id,
                    'original_name' => $album_name,
                    'is_mine' => ($owner_id == $user_id)
                ];
            }
        }
        
        echo json_encode(['success' => true, 'albums' => $all_albums]);
        break;
        
    case 'quiz':
        // Charger les réponses quiz de TOUS les comptes liés
        $theme = $_GET['theme'] ?? '';
        $role = $_GET['role'] ?? '';
        
        $all_answers = [];
        
        foreach($linked_ids as $id){
            $stmt = $conn->prepare("SELECT answers, created_at, user_id FROM quiz_answers WHERE user_id = ? AND theme = ? AND role = ?");
            $stmt->bind_param("iss", $id, $theme, $role);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($row = $result->fetch_assoc()){
                // Récupérer le nom de l'utilisateur
                $user_stmt = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                
                $all_answers[] = [
                    'answers' => json_decode($row['answers'], true),
                    'date' => $row['created_at'],
                    'user_id' => $row['user_id'],
                    'username' => $user['identifiant'],
                    'is_mine' => ($id == $user_id)
                ];
            }
        }
        
        echo json_encode(['success' => true, 'all_answers' => $all_answers]);
        break;
        
    case 'game_scores':
        // Charger les scores de jeu de TOUS les comptes liés
        $game_name = $_GET['game_name'] ?? '';
        
        $all_scores = [];
        
        foreach($linked_ids as $id){
            $stmt = $conn->prepare("SELECT score_data, user_id FROM game_scores WHERE user_id = ? AND game_name = ?");
            $stmt->bind_param("is", $id, $game_name);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($row = $result->fetch_assoc()){
                // Récupérer le nom de l'utilisateur
                $user_stmt = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
                $user_stmt->bind_param("i", $id);
                $user_stmt->execute();
                $user_result = $user_stmt->get_result();
                $user = $user_result->fetch_assoc();
                
                $all_scores[] = [
                    'score_data' => json_decode($row['score_data'], true),
                    'user_id' => $row['user_id'],
                    'username' => $user['identifiant'],
                    'is_mine' => ($id == $user_id)
                ];
            }
        }
        
        echo json_encode(['success' => true, 'all_scores' => $all_scores]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Type inconnu']);
}
?>