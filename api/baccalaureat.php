<?php
session_start();
header('Content-Type: application/json');
include '../db.php';

if(!isset($_SESSION['user_id'])){
    echo json_encode(['success' => false, 'error' => 'Non connecté']);
    exit();
}

$user_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Liste complète des catégories
$ALL_CATEGORIES = [
    "Prénom", "Pays", "Ville", "Animal", "Fruit/Légume", "Métier",
    "Couleur", "Objet", "Marque", "Sport", "Film", "Série TV",
    "Célébrité", "Chanson", "Instrument de musique", "Vêtement",
    "Meuble", "Nourriture", "Boisson", "Fleur/Plante",
    "Personnage fictif", "Livre", "Jeu vidéo", "Langue",
    "Voiture", "Partie du corps", "Maladie", "Sentiment/Émotion"
];

switch($action) {
    case 'create_game':
        // Créer une nouvelle partie
        $game_code = strtoupper(substr(md5(uniqid()), 0, 6));
        
        // Choisir une lettre aléatoire
        $letters = ['A','B','C','D','E','F','G','H','I','J','L','M','N','O','P','Q','R','S','T','U','V'];
        $letter = $letters[array_rand($letters)];
        
        // Choisir 6 catégories aléatoires
        $selected_categories = array_rand(array_flip($ALL_CATEGORIES), 6);
        
        $stmt = $conn->prepare("INSERT INTO baccalaureat_games (game_code, creator_id, letter, categories, status) VALUES (?, ?, ?, ?, 'waiting')");
        $categories_json = json_encode($selected_categories);
        $stmt->bind_param("siss", $game_code, $user_id, $letter, $categories_json);
        
        if($stmt->execute()){
            $game_id = $conn->insert_id;
            echo json_encode([
                'success' => true,
                'game_id' => $game_id,
                'game_code' => $game_code,
                'letter' => $letter,
                'categories' => $selected_categories
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Erreur création']);
        }
        break;
        
    case 'join_game':
        // Rejoindre une partie
        $game_code = $_GET['game_code'] ?? '';
        
        $stmt = $conn->prepare("SELECT id, creator_id, opponent_id, letter, categories, status FROM baccalaureat_games WHERE game_code = ?");
        $stmt->bind_param("s", $game_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($game = $result->fetch_assoc()){
            if($game['creator_id'] == $user_id){
                echo json_encode(['success' => false, 'error' => 'Vous ne pouvez pas rejoindre votre propre partie']);
                break;
            }
            
            if($game['opponent_id'] && $game['opponent_id'] != $user_id){
                echo json_encode(['success' => false, 'error' => 'Partie déjà pleine']);
                break;
            }
            
            // Rejoindre la partie
            $stmt = $conn->prepare("UPDATE baccalaureat_games SET opponent_id = ?, status = 'playing' WHERE id = ?");
            $stmt->bind_param("ii", $user_id, $game['id']);
            $stmt->execute();
            
            echo json_encode([
                'success' => true,
                'game_id' => $game['id'],
                'letter' => $game['letter'],
                'categories' => json_decode($game['categories'], true)
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Partie introuvable']);
        }
        break;
        
    case 'check_status':
        
        $game_id = $_GET['game_id'] ?? 0;
        
        $stmt = $conn->prepare("SELECT status, opponent_id FROM baccalaureat_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($game = $result->fetch_assoc()){
            
            $stmt = $conn->prepare("SELECT user_id, finished_at FROM baccalaureat_answers WHERE game_id = ? AND finished_at IS NOT NULL");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            $answers_result = $stmt->get_result();
            
            $finished_users = [];
            while($row = $answers_result->fetch_assoc()){
                $finished_users[] = $row['user_id'];
            }
            
            echo json_encode([
                'success' => true,
                'status' => $game['status'],
                'opponent_joined' => $game['opponent_id'] != null,
                'finished_users' => $finished_users
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Partie introuvable']);
        }
        break;
        
    case 'save_answers':
        
        $data = json_decode(file_get_contents('php://input'), true);
        $game_id = $data['game_id'];
        $answers = json_encode($data['answers']);
        $is_finished = $data['is_finished'] ?? false;
        
        // Vérifier si des réponses existent déjà
        $check = $conn->prepare("SELECT id FROM baccalaureat_answers WHERE game_id = ? AND user_id = ?");
        $check->bind_param("ii", $game_id, $user_id);
        $check->execute();
        $result = $check->get_result();
        
        if($result->num_rows > 0){
            // Mettre à jour
            if($is_finished){
                $stmt = $conn->prepare("UPDATE baccalaureat_answers SET answers = ?, finished_at = NOW() WHERE game_id = ? AND user_id = ?");
                $stmt->bind_param("sii", $answers, $game_id, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE baccalaureat_answers SET answers = ? WHERE game_id = ? AND user_id = ?");
                $stmt->bind_param("sii", $answers, $game_id, $user_id);
            }
        } else {
            // Insérer
            if($is_finished){
                $stmt = $conn->prepare("INSERT INTO baccalaureat_answers (game_id, user_id, answers, finished_at) VALUES (?, ?, ?, NOW())");
            } else {
                $stmt = $conn->prepare("INSERT INTO baccalaureat_answers (game_id, user_id, answers) VALUES (?, ?, ?)");
            }
            $stmt->bind_param("iis", $game_id, $user_id, $answers);
        }
        
        $stmt->execute();
        echo json_encode(['success' => true]);
        break;
        
    case 'get_results':
        // Récupérer les résultats
        $game_id = $_GET['game_id'] ?? 0;
        
        // Récupérer les infos du jeu
        $stmt = $conn->prepare("SELECT letter, categories, creator_id, opponent_id FROM baccalaureat_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();
        
        // Récupérer les réponses des deux joueurs
        $stmt = $conn->prepare("
            SELECT ba.user_id, ba.answers, ba.finished_at, u.identifiant 
            FROM baccalaureat_answers ba
            INNER JOIN users u ON ba.user_id = u.id
            WHERE ba.game_id = ?
        ");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $players = [];
        while($row = $result->fetch_assoc()){
            $players[$row['user_id']] = [
                'username' => $row['identifiant'],
                'answers' => json_decode($row['answers'], true),
                'finished_at' => $row['finished_at']
            ];
        }
        
        // Calculer les points
        $categories = json_decode($game['categories'], true);
        $scores = [];
        
        foreach($players as $uid => $player){
            $score = 0;
            foreach($categories as $index => $category){
                $answer = $player['answers'][$index] ?? '';
                if(!empty($answer)){
                    // Vérifier si l'autre joueur a la même réponse
                    $is_unique = true;
                    foreach($players as $other_uid => $other_player){
                        if($other_uid != $uid){
                            $other_answer = $other_player['answers'][$index] ?? '';
                            if(strtolower(trim($answer)) === strtolower(trim($other_answer))){
                                $is_unique = false;
                                break;
                            }
                        }
                    }
                    $score += $is_unique ? 10 : 5; // 10 points si unique, 5 si doublon
                }
            }
            $scores[$uid] = $score;
        }
        
        echo json_encode([
            'success' => true,
            'letter' => $game['letter'],
            'categories' => $categories,
            'players' => $players,
            'scores' => $scores
        ]);
        break;
        
    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
}
?>