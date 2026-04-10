<?php
session_start();
include "db.php";

// Si déjà connecté, rediriger vers index.php
if(isset($_SESSION['user_id'])){
    header("Location: index.php");
    exit();
}

$message = "";
$message_type = "";

if(isset($_POST['identifiant']) && isset($_POST['password'])){
    $identifiant = trim($_POST['identifiant']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, password FROM users WHERE identifiant=?");
    $stmt->bind_param("s", $identifiant);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1){
        $user = $result->fetch_assoc();
        if(password_verify($password, $user['password'])){
            // ✅ Connexion réussie
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_identifiant'] = $identifiant;
            
            header("Location: index.php");
            exit();
        } else {
            $message = "Mot de passe incorrect";
            $message_type = "error";
        }
    } else {
        $message = "Identifiant non trouvé";
        $message_type = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - BridgeLink</title>
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
            <h1 class="text-3xl font-bold text-gray-800 mb-2">Bon retour !</h1>
            <p class="text-gray-600">Connectez-vous à votre espace BridgeLink</p>
        </div>

        <?php if($message != ""): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-50 border-l-4 border-red-500">
                <p class="text-red-700 font-medium flex items-center gap-2">
                    <i data-lucide="alert-circle" class="w-5 h-5"></i>
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
                    placeholder="Votre identifiant" 
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
                    placeholder="Votre mot de passe" 
                    required
                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-indigo-500 focus:ring-0 transition-colors"
                >
            </div>

            <button 
                type="submit"
                class="w-full bg-gradient-to-r from-indigo-500 to-purple-600 hover:from-indigo-600 hover:to-purple-700 text-white font-bold py-4 rounded-xl transition-all shadow-lg hover:shadow-xl flex items-center justify-center gap-2"
            >
                <i data-lucide="log-in" class="w-5 h-5"></i>
                Se connecter
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-600">
                Pas encore de compte ? 
                <a href="register.php" class="text-indigo-600 font-semibold hover:text-indigo-700 transition-colors">
                    Créer un compte
                </a>
            </p>
        </div>

        <div class="mt-8 pt-6 border-t border-gray-200 text-center">
            <p class="text-sm text-gray-500 flex items-center justify-center gap-2">
                <i data-lucide="shield-check" class="w-4 h-4"></i>
                Vos données sont sécurisées
            </p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>