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

// Banque de mots par catégorie (le créateur choisit)
$WORDS = [
    'Animaux'    => ['ELEPHANT','GIRAFE','PINGOUIN','CROCODILE','PAPILLON','DAUPHIN','KOALA','TORTUE','PERROQUET','LION','ZEBRE','PANDA','RENARD','LAPIN','HIBOU','REQUIN','FOURMI','BALEINE','JAGUAR','COLIBRI'],
    'Pays'       => ['FRANCE','BRESIL','JAPON','AUSTRALIE','CANADA','MEXIQUE','EGYPTE','INDE','RUSSIE','CHINE','ESPAGNE','ITALIE','ALLEMAGNE','PORTUGAL','MAROC','PEROU','KENYA','VIETNAM','FINLANDE','ISLANDE'],
    'Films'      => ['AVATAR','INCEPTION','TITANIC','MATRIX','SHREK','INTERSTELLAR','COCO','ENCANTO','MULAN','RATATOUILLE','FROZEN','MOANA','BAMBI','DUMBO','PINOCCHIO','TARZAN','HERCULE','ALADDIN','ZOOTOPIE','REBELLE'],
    'Sports'     => ['FOOTBALL','TENNIS','NATATION','CYCLISME','BOXE','RUGBY','VOLLEYBALL','BASKETBALL','BASEBALL','HANDBALL','JUDO','KARATE','GOLF','SURF','ESCALADE','TRIATHLON','BIATHLON','BADMINTON','ESCRIME','PLONGEON'],
    'Nourriture' => ['PIZZA','SUSHI','CROISSANT','HAMBURGER','LASAGNE','RATATOUILLE','GUACAMOLE','CREPE','FONDUE','RACLETTE','TIRAMISU','MACARON','PROFITEROLE','QUICHE','TARTIFLETTE','BOUILLABAISSE','COUSCOUS','PAELLA','WAFFLES','CHURROS'],
    'Objets'     => ['PARAPLUIE','TELEPHONE','CASSEROLE','TELESCOPE','MICROSCOPE','ORDINATEUR','BIBLIOTHEQUE','ASPIRATEUR','BAIGNOIRE','REFRIGERATEUR','TRAMPOLINE','SKATEBOARD','ACCORDEON','SAXOPHONE','XYLOPHONE','KALEDOSCOPE','BOUSSOLE','IMPERMEABLE','CHAUSSETTE','DICTIONNAIRE'],
];

switch($action) {

    // ===== CRÉER UNE PARTIE =====
    case 'create_game':
        $game_code = strtoupper(substr(md5(uniqid()), 0, 6));
        $category  = $_GET['category'] ?? 'Animaux';
        if(!isset($WORDS[$category])) $category = 'Animaux';

        // Choisir un mot aléatoire
        $word = $WORDS[$category][array_rand($WORDS[$category])];

        // Récupérer le nom du créateur
        $stmt = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $creator_name = $stmt->get_result()->fetch_assoc()['identifiant'];

        $stmt = $conn->prepare("INSERT INTO pendu_games (game_code, creator_id, word, category, status, letters_guessed, errors, guesser_id) VALUES (?, ?, ?, ?, 'waiting', '[]', 0, NULL)");
        $stmt->bind_param("siss", $game_code, $user_id, $word, $category);

        if($stmt->execute()){
            $game_id = $conn->insert_id;
            echo json_encode([
                'success'      => true,
                'game_id'      => $game_id,
                'game_code'    => $game_code,
                'word'         => $word,         // Le créateur voit le mot
                'category'     => $category,
                'creator_name' => $creator_name
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    // ===== REJOINDRE UNE PARTIE =====
    case 'join_game':
        $game_code = strtoupper(trim($_GET['game_code'] ?? ''));

        $stmt = $conn->prepare("SELECT id, creator_id, status FROM pendu_games WHERE game_code = ?");
        $stmt->bind_param("s", $game_code);
        $stmt->execute();
        $result = $stmt->get_result();

        if(!($game = $result->fetch_assoc())){
            echo json_encode(['success' => false, 'error' => 'Code invalide']);
            break;
        }
        if($game['creator_id'] == $user_id){
            echo json_encode(['success' => false, 'error' => 'Tu ne peux pas rejoindre ta propre partie']);
            break;
        }
        if($game['status'] === 'finished'){
            echo json_encode(['success' => false, 'error' => 'Partie déjà terminée']);
            break;
        }

        // Récupérer le nom du joueur
        $stmt2 = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
        $stmt2->bind_param("i", $user_id);
        $stmt2->execute();
        $guesser_name = $stmt2->get_result()->fetch_assoc()['identifiant'];

        // Enregistrer le devineur et démarrer
        $stmt3 = $conn->prepare("UPDATE pendu_games SET guesser_id = ?, guesser_name = ?, status = 'playing' WHERE id = ?");
        $stmt3->bind_param("isi", $user_id, $guesser_name, $game['id']);
        $stmt3->execute();

        echo json_encode([
            'success'      => true,
            'game_id'      => $game['id'],
            'guesser_name' => $guesser_name
        ]);
        break;

    // ===== DEVINER UNE LETTRE =====
    case 'guess_letter':
        $game_id = intval($_GET['game_id'] ?? 0);
        $letter  = strtoupper(trim($_GET['letter'] ?? ''));

        if(!$letter || strlen($letter) !== 1){
            echo json_encode(['success' => false, 'error' => 'Lettre invalide']);
            break;
        }

        $stmt = $conn->prepare("SELECT creator_id, guesser_id, word, letters_guessed, errors, status FROM pendu_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();

        if(!$game || $game['status'] !== 'playing'){
            echo json_encode(['success' => false, 'error' => 'Partie non active']);
            break;
        }
        // Seul le devineur peut proposer des lettres
        if($game['guesser_id'] != $user_id){
            echo json_encode(['success' => false, 'error' => 'Seul le devineur peut jouer']);
            break;
        }

        $letters = json_decode($game['letters_guessed'], true) ?: [];
        if(in_array($letter, $letters)){
            echo json_encode(['success' => false, 'error' => 'Lettre déjà jouée']);
            break;
        }

        $letters[] = $letter;
        $word   = $game['word'];
        $errors = $game['errors'];

        // Vérifier si la lettre est dans le mot
        if(strpos($word, $letter) === false) $errors++;

        $letters_json = json_encode($letters);

        // Vérifier victoire : toutes les lettres du mot ont été trouvées
        $won  = true;
        for($i = 0; $i < strlen($word); $i++){
            if(!in_array($word[$i], $letters)){ $won = false; break; }
        }
        $lost = ($errors >= 7);

        $new_status = 'playing';
        if($won)  $new_status = 'won';
        if($lost) $new_status = 'lost';

        $stmt = $conn->prepare("UPDATE pendu_games SET letters_guessed = ?, errors = ?, status = ? WHERE id = ?");
        $stmt->bind_param("sisi", $letters_json, $errors, $new_status, $game_id);
        $stmt->execute();

        echo json_encode([
            'success'  => true,
            'correct'  => strpos($word, $letter) !== false,
            'errors'   => $errors,
            'status'   => $new_status,
            'letters'  => $letters
        ]);
        break;

    // ===== DEVINER LE MOT ENTIER =====
    case 'guess_word':
        $game_id = intval($_GET['game_id'] ?? 0);
        $data    = json_decode(file_get_contents('php://input'), true);
        $guess   = strtoupper(trim($data['word'] ?? ''));

        $stmt = $conn->prepare("SELECT guesser_id, word, errors, status FROM pendu_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();

        if(!$game || $game['status'] !== 'playing'){
            echo json_encode(['success' => false, 'error' => 'Partie non active']);
            break;
        }
        if($game['guesser_id'] != $user_id){
            echo json_encode(['success' => false, 'error' => 'Seul le devineur peut jouer']);
            break;
        }

        if($guess === $game['word']){
            $stmt = $conn->prepare("UPDATE pendu_games SET status = 'won' WHERE id = ?");
            $stmt->bind_param("i", $game_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'correct' => true]);
        } else {
            // Mauvaise tentative = +2 erreurs
            $errors = min(7, $game['errors'] + 2);
            $new_status = $errors >= 7 ? 'lost' : 'playing';
            $stmt = $conn->prepare("UPDATE pendu_games SET errors = ?, status = ? WHERE id = ?");
            $stmt->bind_param("isi", $errors, $new_status, $game_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'correct' => false, 'errors' => $errors, 'status' => $new_status]);
        }
        break;

    // ===== REJOUER (créateur choisit un nouveau mot) =====
    case 'rematch':
        $game_id  = intval($_GET['game_id'] ?? 0);
        $category = $_GET['category'] ?? 'Animaux';
        if(!isset($WORDS[$category])) $category = 'Animaux';

        $stmt = $conn->prepare("SELECT creator_id, guesser_id, guesser_name FROM pendu_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();

        if(!$game || $game['creator_id'] != $user_id){
            echo json_encode(['success' => false, 'error' => 'Non autorisé']);
            break;
        }

        $word         = $WORDS[$category][array_rand($WORDS[$category])];
        $game_code    = strtoupper(substr(md5(uniqid()), 0, 6));
        $guesser_id   = $game['guesser_id'];
        $guesser_name = $game['guesser_name'];

        $stmt = $conn->prepare("INSERT INTO pendu_games (game_code, creator_id, guesser_id, guesser_name, word, category, status, letters_guessed, errors) VALUES (?, ?, ?, ?, ?, ?, 'playing', '[]', 0)");
        $stmt->bind_param("siisss", $game_code, $user_id, $guesser_id, $guesser_name, $word, $category);

        if($stmt->execute()){
            $new_game_id = $conn->insert_id;
            echo json_encode([
                'success'   => true,
                'game_id'   => $new_game_id,
                'game_code' => $game_code,
                'word'      => $word,
                'category'  => $category
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        break;

    // ===== POLLING =====
    case 'poll':
        $game_id = intval($_GET['game_id'] ?? 0);

        $stmt = $conn->prepare("SELECT * FROM pendu_games WHERE id = ?");
        $stmt->bind_param("i", $game_id);
        $stmt->execute();
        $game = $stmt->get_result()->fetch_assoc();

        if(!$game){
            echo json_encode(['success' => false, 'error' => 'Partie introuvable']);
            break;
        }

        $am_creator = ($game['creator_id'] == $user_id);
        $am_guesser = ($game['guesser_id'] == $user_id);

        // Récupérer les noms
        $stmt2 = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
        $stmt2->bind_param("i", $game['creator_id']);
        $stmt2->execute();
        $creator_name = $stmt2->get_result()->fetch_assoc()['identifiant'];

        $word    = $game['word'];
        $letters = json_decode($game['letters_guessed'], true) ?: [];

        // Construire le mot masqué
        $masked = [];
        for($i = 0; $i < strlen($word); $i++){
            $masked[] = in_array($word[$i], $letters) ? $word[$i] : '_';
        }

        echo json_encode([
            'success'      => true,
            'status'       => $game['status'],
            'category'     => $game['category'],
            'word_length'  => strlen($word),
            'masked_word'  => $masked,
            'letters'      => $letters,
            'errors'       => $game['errors'],
            'max_errors'   => 7,
            'am_creator'   => $am_creator,
            'am_guesser'   => $am_guesser,
            'creator_name' => $creator_name,
            'guesser_name' => $game['guesser_name'] ?? null,
            // Le mot complet est révélé au créateur tout le temps, et à tous en fin de partie
            'word'         => ($am_creator || in_array($game['status'], ['won','lost'])) ? $word : null,
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Action inconnue']);
}
?>
