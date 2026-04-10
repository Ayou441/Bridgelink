<?php
session_start();
include "db.php";

$message = "";
$message_type = "";

if(isset($_POST['identifiant']) && isset($_POST['password'])){
    $identifiant = trim($_POST['identifiant']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    $check = $conn->prepare("SELECT id FROM users WHERE identifiant=?");
    $check->bind_param("s", $identifiant);
    $check->execute();
    $result = $check->get_result();
    
    if($result->num_rows > 0){
        $message = "Identifiant déjà utilisé";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (identifiant, password) VALUES (?, ?)");
        $stmt->bind_param("ss", $identifiant, $password);
        
        if($stmt->execute()){
            // ✅ Créer la session automatiquement
            $_SESSION['user_id'] = $conn->insert_id;
            $_SESSION['user_identifiant'] = $identifiant;
            
            // ✅ Rediriger vers la page d'accueil (AVANT tout HTML)
            header("Location: index.php");
            exit();
        } else {
            $message = "Erreur lors de la création du compte.";
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
    <title>Inscription - BridgeLink</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body class="gradient-bg min-h-screen flex items-center justify-center p-4">
    
    <div class="bg-white rounded-3xl shadow-2xl p-8 w-full max-w-md">
        <div class="text-center mb-8">
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 w-20 h-20 rounded-2xl flex items-center justify-center mx-auto mb-4 animate-float">
                <i data-lucide="heart" class="w-10 h-10 text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Créer un compte</h1>
            <p class="text-gray-600">Rejoignez BridgeLink aujourd'hui !</p>
        </div>

        <?php if($message != ""): ?>
            <div class="mb-6 p-4 rounded-xl <?php echo ($message_type == 'error') ? 'bg-red-50 border-l-4 border-red-500' : 'bg-green-50 border-l-4 border-green-500'; ?>">
                <p class="<?php echo ($message_type == 'error') ? 'text-red-700' : 'text-green-700'; ?> font-medium">
                    <?php echo $message; ?>
                </p>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-gray-700 font-semibold mb-2">
                    <i data-lucide="user" class="w-4 h-4 inline mr-2"></i>
                    Identifiant
                </label>
                <input 
                    type="text" 
                    name="identifiant" 
                    placeholder="Choisissez un identifiant" 
                    required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-0 transition-colors"
                >
            </div>

            <div>
                <label class="block text-gray-700 font-semibold mb-2">
                    <i data-lucide="lock" class="w-4 h-4 inline mr-2"></i>
                    Mot de passe
                </label>
                <input 
                    type="password" 
                    name="password" 
                    placeholder="Créez un mot de passe sécurisé" 
                    required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-0 transition-colors"
                >
            </div>

            <button 
                type="submit"
                class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold py-4 rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
            >
                <i data-lucide="user-plus" class="w-5 h-5"></i>
                Créer mon compte
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Vous avez déjà un compte ? 
                <a href="login.php" class="text-indigo-600 font-semibold hover:text-indigo-700 transition-colors">
                    Se connecter
                </a>
            </p>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
            <p class="text-sm text-gray-500">
                En créant un compte, vous acceptez nos conditions d'utilisation
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>