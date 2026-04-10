<?php
session_start();
include "db.php";

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

// Récupérer les infos de l'utilisateur
$stmt = $conn->prepare("SELECT identifiant FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Récupérer les liens existants
$linked_users = [];
$stmt = $conn->prepare("
    SELECT u.id, u.identifiant, 
           CASE 
               WHEN fl.parent_id = ? THEN 'Enfant'
               WHEN fl.child_id = ? THEN 'Parent'
           END as relation
    FROM users u
    INNER JOIN family_links fl ON (u.id = fl.parent_id OR u.id = fl.child_id)
    WHERE (fl.parent_id = ? OR fl.child_id = ?) AND u.id != ?
");
$stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
while($row = $result->fetch_assoc()){
    $linked_users[] = $row;
}

// Traiter la demande de liaison
if(isset($_POST['link_identifiant']) && isset($_POST['role'])){
    $link_identifiant = trim($_POST['link_identifiant']);
    $role = $_POST['role'];
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE identifiant = ?");
    $stmt->bind_param("s", $link_identifiant);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 0){
        $message = "Utilisateur non trouvé";
        $message_type = "error";
    } else {
        $other_user = $result->fetch_assoc();
        $other_id = $other_user['id'];
        
        if($other_id == $user_id){
            $message = "Vous ne pouvez pas vous lier à vous-même";
            $message_type = "error";
        } else {
            if($role == 'parent'){
                $parent_id = $user_id;
                $child_id = $other_id;
            } else {
                $parent_id = $other_id;
                $child_id = $user_id;
            }
            
            $check = $conn->prepare("SELECT id FROM family_links WHERE parent_id = ? AND child_id = ?");
            $check->bind_param("ii", $parent_id, $child_id);
            $check->execute();
            $check_result = $check->get_result();
            
            if($check_result->num_rows > 0){
                $message = "Vous êtes déjà liés";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("INSERT INTO family_links (parent_id, child_id) VALUES (?, ?)");
                $stmt->bind_param("ii", $parent_id, $child_id);
                
                if($stmt->execute()){
                    $message = "Liaison créée avec succès !";
                    $message_type = "success";
                    header("Location: settings.php");
                    exit();
                } else {
                    $message = "Erreur lors de la création du lien";
                    $message_type = "error";
                }
            }
        }
    }
}

// Supprimer un lien
if(isset($_GET['unlink'])){
    $unlink_id = intval($_GET['unlink']);
    
    $stmt = $conn->prepare("DELETE FROM family_links WHERE (parent_id = ? AND child_id = ?) OR (parent_id = ? AND child_id = ?)");
    $stmt->bind_param("iiii", $user_id, $unlink_id, $unlink_id, $user_id);
    
    if($stmt->execute()){
        header("Location: settings.php");
        exit();
    }
}

// Changer le mot de passe
if(isset($_POST['change_password'])){
    $old_password = $_POST['old_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Vérifier l'ancien mot de passe
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    
    if(!password_verify($old_password, $user_data['password'])){
        $message = "Ancien mot de passe incorrect";
        $message_type = "error";
    } else if($new_password !== $confirm_password){
        $message = "Les nouveaux mots de passe ne correspondent pas";
        $message_type = "error";
    } else if(strlen($new_password) < 6){
        $message = "Le mot de passe doit contenir au moins 6 caractères";
        $message_type = "error";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed, $user_id);
        
        if($stmt->execute()){
            $message = "Mot de passe modifié avec succès !";
            $message_type = "success";
        } else {
            $message = "Erreur lors de la modification";
            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paramètres - BridgeLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="bg-gray-50">
    
    <!-- Header -->
    <header class="bg-white shadow-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <a href="index.php" class="flex items-center gap-3">
                    <div class="bg-gradient-to-r from-indigo-500 to-purple-600 p-2 rounded-xl">
                        <i data-lucide="heart" class="w-6 h-6 text-white"></i>
                    </div>
                    <span class="text-2xl font-bold gradient-text">BridgeLink</span>
                </a>
                
                <a href="index.php" class="flex items-center gap-2 text-gray-600 hover:text-purple-600 font-semibold transition-colors">
                    <i data-lucide="arrow-left"></i>
                    Retour
                </a>
            </div>
        </div>
    </header>

    <div class="max-w-6xl mx-auto px-4 py-8">
        
        <!-- Titre principal -->
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-gray-800 mb-2">Paramètres</h1>
            <p class="text-gray-600">Gérez votre compte et vos préférences</p>
        </div>

        <?php if($message != ""): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo ($message_type == 'error') ? 'bg-red-50 border-l-4 border-red-500' : 'bg-green-50 border-l-4 border-green-500'; ?>">
                <p class="<?php echo ($message_type == 'error') ? 'text-red-700' : 'text-green-700'; ?> font-medium flex items-center gap-2">
                    <i data-lucide="<?php echo ($message_type == 'error') ? 'alert-circle' : 'check-circle'; ?>" class="w-5 h-5"></i>
                    <?php echo $message; ?>
                </p>
            </div>
        <?php endif; ?>

        <div class="grid lg:grid-cols-3 gap-6">
            
            <!-- Colonne de gauche : Infos du compte -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Carte profil -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <div class="text-center">
                        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="user" class="w-10 h-10 text-white"></i>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-800 mb-1">
                            <?php echo htmlspecialchars($user['identifiant']); ?>
                        </h2>
                        <p class="text-gray-500 text-sm">Votre identifiant</p>
                    </div>
                </div>

                <!-- Carte actions rapides -->
                <div class="bg-white rounded-3xl shadow-xl p-6">
                    <h3 class="text-lg font-bold text-gray-800 mb-4 flex items-center gap-2">
                        <i data-lucide="zap" class="text-yellow-500"></i>
                        Actions rapides
                    </h3>
                    
                    <div class="space-y-2">
                        <a href="admin_logs.php" class="block w-full text-left px-4 py-3 hover:bg-gray-50 rounded-xl transition-colors flex items-center gap-3 text-gray-700">
                            <i data-lucide="bar-chart" class="w-5 h-5"></i>
                            Voir les logs
                        </a>
                        
                        <a href="logout.php" class="block w-full text-left px-4 py-3 hover:bg-red-50 rounded-xl transition-colors flex items-center gap-3 text-red-600">
                            <i data-lucide="log-out" class="w-5 h-5"></i>
                            Déconnexion
                        </a>
                    </div>
                </div>
            </div>

            <!-- Colonne de droite : Paramètres détaillés -->
            <div class="lg:col-span-2 space-y-6">
                
                <!-- Section Liaison familiale -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                        <i data-lucide="users" class="text-purple-500"></i>
                        Liaison familiale
                    </h2>

                    <div class="grid md:grid-cols-2 gap-6">
                        
                        <!-- Créer une liaison -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Créer une liaison</h3>
                            
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2 text-sm">
                                        Identifiant de la personne
                                    </label>
                                    <input 
                                        type="text" 
                                        name="link_identifiant" 
                                        placeholder="Ex: papa123" 
                                        required
                                        class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:ring-0"
                                    >
                                </div>

                                <div>
                                    <label class="block text-gray-700 font-semibold mb-2 text-sm">
                                        Je suis le/la :
                                    </label>
                                    <div class="space-y-2">
                                        <label class="flex items-center gap-2 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-purple-300">
                                            <input type="radio" name="role" value="parent" required class="w-4 h-4 text-purple-600">
                                            <span class="text-sm font-medium">Parent (l'autre est mon enfant)</span>
                                        </label>
                                        
                                        <label class="flex items-center gap-2 p-3 border-2 border-gray-200 rounded-xl cursor-pointer hover:border-pink-300">
                                            <input type="radio" name="role" value="child" required class="w-4 h-4 text-pink-600">
                                            <span class="text-sm font-medium">Enfant (l'autre est mon parent)</span>
                                        </label>
                                    </div>
                                </div>

                                <button 
                                    type="submit"
                                    class="w-full bg-gradient-to-r from-purple-500 to-pink-500 hover:from-purple-600 hover:to-pink-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2"
                                >
                                    <i data-lucide="user-plus"></i>
                                    Créer la liaison
                                </button>
                            </form>
                        </div>

                        <!-- Liens existants -->
                        <div>
                            <h3 class="text-lg font-bold text-gray-800 mb-4">Mes liens (<?php echo count($linked_users); ?>)</h3>
                            
                            <?php if(count($linked_users) == 0): ?>
                                <div class="text-center py-8 bg-gray-50 rounded-xl">
                                    <i data-lucide="users-round" class="w-12 h-12 mx-auto text-gray-300 mb-2"></i>
                                    <p class="text-gray-500 text-sm">Aucune liaison</p>
                                </div>
                            <?php else: ?>
                                <div class="space-y-3">
                                    <?php foreach($linked_users as $linked): ?>
                                        <div class="flex items-center justify-between p-4 bg-gradient-to-r from-purple-50 to-pink-50 rounded-xl">
                                            <div class="flex items-center gap-3">
                                                <div class="bg-white p-2 rounded-full">
                                                    <i data-lucide="user" class="w-5 h-5 text-purple-600"></i>
                                                </div>
                                                <div>
                                                    <p class="font-bold text-gray-800"><?php echo htmlspecialchars($linked['identifiant']); ?></p>
                                                    <p class="text-xs text-gray-600"><?php echo $linked['relation']; ?></p>
                                                </div>
                                            </div>
                                            <a 
                                                href="?unlink=<?php echo $linked['id']; ?>" 
                                                onclick="return confirm('Supprimer ce lien ?')"
                                                class="text-red-500 hover:text-red-600 p-2 hover:bg-red-50 rounded-lg transition-colors"
                                            >
                                                <i data-lucide="trash-2" class="w-4 h-4"></i>
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info box -->
                    <div class="mt-6 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl">
                        <div class="flex items-start gap-3">
                            <i data-lucide="info" class="text-blue-500 w-5 h-5 mt-1"></i>
                            <div>
                                <p class="text-blue-800 font-medium text-sm mb-1">Comment ça marche ?</p>
                                <ul class="text-blue-700 text-xs space-y-1">
                                    <li>• Demandez l'identifiant de votre parent ou enfant</li>
                                    <li>• Vos données seront séparées et synchronisées</li>
                                    <li>• Vous pouvez avoir plusieurs liens familiaux</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section Sécurité -->
                <div class="bg-white rounded-3xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                        <i data-lucide="shield" class="text-green-500"></i>
                        Sécurité
                    </h2>

                    <form method="POST" class="max-w-md space-y-4">
                        <div>
                            <label class="block text-gray-700 font-semibold mb-2 text-sm">
                                Ancien mot de passe
                            </label>
                            <input 
                                type="password" 
                                name="old_password" 
                                required
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0"
                            >
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2 text-sm">
                                Nouveau mot de passe
                            </label>
                            <input 
                                type="password" 
                                name="new_password" 
                                required
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0"
                            >
                        </div>

                        <div>
                            <label class="block text-gray-700 font-semibold mb-2 text-sm">
                                Confirmer le nouveau mot de passe
                            </label>
                            <input 
                                type="password" 
                                name="confirm_password" 
                                required
                                class="w-full px-4 py-2 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0"
                            >
                        </div>

                        <button 
                            type="submit"
                            name="change_password"
                            class="w-full bg-gradient-to-r from-green-500 to-green-600 hover:from-green-600 hover:to-green-700 text-white font-bold py-3 rounded-xl transition-all shadow-lg flex items-center justify-center gap-2"
                        >
                            <i data-lucide="lock"></i>
                            Changer le mot de passe
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>