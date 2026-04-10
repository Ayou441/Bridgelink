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

switch($type){
    case 'albums':
        // Charger tous les albums avec leurs photos
        $albums = [];
        $stmt = $conn->prepare("SELECT id, album_name FROM albums WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while($album = $result->fetch_assoc()){
            $album_id = $album['id'];
            $photos_stmt = $conn->prepare("SELECT photo_name, photo_data FROM album_photos WHERE album_id = ? ORDER BY created_at");
            $photos_stmt->bind_param("i", $album_id);
            $photos_stmt->execute();
            $photos_result = $photos_stmt->get_result();
            
            $photos = [];
            while($photo = $photos_result->fetch_assoc()){
                $photos[] = $photo;
            }
            
            $albums[$album['album_name']] = $photos;
        }
        
        echo json_encode(['success' => true, 'albums' => $albums]);
        break;
        
    case 'quiz':
        // Charger réponses quiz
        $theme = $_GET['theme'] ?? '';
        $role = $_GET['role'] ?? '';
        
        $stmt = $conn->prepare("SELECT answers, created_at FROM quiz_answers WHERE user_id = ? AND theme = ? AND role = ?");
        $stmt->bind_param("iss", $user_id, $theme, $role);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()){
            echo json_encode([
                'success' => true,
                'answers' => json_decode($row['answers'], true),
                'date' => $row['created_at']
            ]);
        } else {
            echo json_encode(['success' => true, 'answers' => null]);
        }
        break;
        
    case 'game_score':
        // Charger score de jeu
        $game_name = $_GET['game_name'] ?? '';
        
        $stmt = $conn->prepare("SELECT score_data FROM game_scores WHERE user_id = ? AND game_name = ?");
        $stmt->bind_param("is", $user_id, $game_name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($row = $result->fetch_assoc()){
            echo json_encode([
                'success' => true,
                'score_data' => json_decode($row['score_data'], true)
            ]);
        } else {
            echo json_encode(['success' => true, 'score_data' => null]);
        }
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Type inconnu']);
}
?>