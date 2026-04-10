<?php
session_start();

// Vérifier si l'utilisateur est connecté
if(!isset($_SESSION['user_id'])){
    // Si non connecté, rediriger vers la page de login
    header("Location: login.php");
    exit();
}

include 'auto_track.php';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BridgeLink - Votre espace famille</title>

    <!-- React & ReactDOM -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>

    <!-- Babel for JSX -->
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <!-- Tracking JS -->
    <script src="track.js"></script>
    
    <!-- PWA Manifest -->
	<link rel="manifest" href="/manifest.json">

    <link rel="apple-touch-startup-image" href="/splash.png">

    
	<!-- iOS support -->
	<meta name="apple-mobile-web-app-capable" content="yes">
	<meta name="apple-mobile-web-app-status-bar-style" content="default">
	<meta name="apple-mobile-web-app-title" content="BridgeLink">
	<link rel="apple-touch-icon" href="/icons/icon-192.png">

	<meta name="theme-color" content="#4f46e5">


    <style>
        .animate-float {
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0px);
            }
            50% {
                transform: translateY(-10px);
            }
        }

        .gradient-text {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .fade-in-up {
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .wave-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
            overflow: hidden;
        }

        .wave-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 200%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            animation: wave 3s infinite;
        }

        @keyframes wave {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .animate-bounce-slow {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {
            0%, 100% {
                transform: translateY(-5%);
                animation-timing-function: cubic-bezier(0.8, 0, 1, 1);
            }
            50% {
                transform: translateY(0);
                animation-timing-function: cubic-bezier(0, 0, 0.2, 1);
            }
        }

        .animate-spin-slow {
            animation: spin 3s linear infinite;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }

        .game-card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .game-card-hover:hover {
            transform: translateY(-8px) scale(1.02);
        }
    </style>
</head>

<body class="bg-gray-50">
    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect } = React;

        // --- TRACKING SYSTEM ---
        const tracking = {
            logEvent: (eventType, details = {}) => {
                const event = {
                    timestamp: new Date().toISOString(),
                    eventType,
                    details,
                    sessionId: tracking.getSessionId()
                };

                const history = tracking.getHistory();
                history.push(event);
                localStorage.setItem('tracking_history', JSON.stringify(history));
                console.log('📊 Event tracked:', event);
                
                // Aussi envoyer au serveur PHP via track.js
                if (typeof trackAction === 'function') {
                    trackAction(eventType);
                }
            },

            getSessionId: () => {
                let sessionId = sessionStorage.getItem('tracking_session_id');
                if (!sessionId) {
                    sessionId = `session_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
                    sessionStorage.setItem('tracking_session_id', sessionId);
                }
                return sessionId;
            },

            getHistory: () => {
                const history = localStorage.getItem('tracking_history');
                return history ? JSON.parse(history) : [];
            },

            getStats: () => {
                const history = tracking.getHistory();
                
                const stats = {
                    totalEvents: history.length,
                    byType: {},
                    bySessions: {},
                    timeline: []
                };

                history.forEach(event => {
                    stats.byType[event.eventType] = (stats.byType[event.eventType] || 0) + 1;
                    stats.bySessions[event.sessionId] = (stats.bySessions[event.sessionId] || 0) + 1;
                });

                stats.timeline = history;
                return stats;
            },

            clearHistory: () => {
                localStorage.removeItem('tracking_history');
                console.log('🗑️ Tracking history cleared');
            },

            exportToCSV: () => {
                const history = tracking.getHistory();
                if (history.length === 0) {
                    alert('Aucune donnée à exporter');
                    return;
                }

                const headers = ['Timestamp', 'Type d\'événement', 'Détails', 'Session ID'];
                const rows = history.map(event => [
                    event.timestamp,
                    event.eventType,
                    JSON.stringify(event.details),
                    event.sessionId
                ]);

                const csv = [
                    headers.join(','),
                    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
                ].join('\n');

                const blob = new Blob([csv], { type: 'text/csv' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `tracking_data_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
            }
        };

        const App = () => {
            const [activeTab, setActiveTab] = useState('accueil');
            const [showProfileMenu, setShowProfileMenu] = useState(false);

            useEffect(() => {
                lucide.createIcons();
                tracking.logEvent('PAGE_LOAD', { screen: activeTab });
            }, [activeTab]);

            // Menu items
            const menuItems = [
                { id: 'accueil', label: 'Accueil', icon: 'home' },
                { id: 'jeux', label: 'Jeux', icon: 'gamepad-2' },
                { id: 'quiz', label: 'Quiz', icon: 'help-circle' },
                { id: 'activites', label: 'Activités', icon: 'sparkles' },
                { id: 'albums', label: 'Albums', icon: 'images' }
            ];

            // Header simple avec profil
            const Header = () => (
                <header className="bg-white shadow-md sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between items-center h-16">
                            {/* Logo */}
                            <div className="flex items-center gap-3">
                                <div className="bg-gradient-to-r from-indigo-500 to-purple-600 p-2 rounded-xl">
                                    <i data-lucide="heart" className="w-6 h-6 text-white"></i>
                                </div>
                                <span className="text-2xl font-bold gradient-text hidden sm:block">
                                    BridgeLink
                                </span>
                                <span className="text-xl font-bold gradient-text sm:hidden">
                                    BridgeLink
                                </span>
                            </div>

                            {/* Profil */}
                            <div className="relative">
                                <button
                                    onClick={() => {
                                        setShowProfileMenu(!showProfileMenu);
                                        tracking.logEvent('PROFILE_MENU_TOGGLED', { opened: !showProfileMenu });
                                    }}
                                    data-track="profile_menu_toggle"
                                    className="bg-gradient-to-r from-indigo-500 to-purple-600 p-2 rounded-full hover:shadow-lg transition-all"
                                >
                                    <i data-lucide="user" className="w-6 h-6 text-white"></i>
                                </button>

                                {/* Menu déroulant profil */}
                                {showProfileMenu && (
                                    <div className="absolute right-0 mt-2 w-48 bg-white rounded-xl shadow-2xl py-2 border border-gray-100 fade-in-up">
                                        {/* <button 
                                            onClick={() => tracking.logEvent('PROFILE_CLICKED')}
                                            data-track="menu_profile"
                                            className="w-full text-left px-4 py-2 hover:bg-gray-50 flex items-center gap-3 text-gray-700"
                                        >
                                            <i data-lucide="user" className="w-4 h-4"></i>
                                            Mon profil
                                        </button> */}
                                        <button 
    onClick={() => window.location.href = 'settings.php'}
    data-track="menu_settings"
    className="w-full text-left px-4 py-2 hover:bg-gray-50 flex items-center gap-3 text-gray-700"
>
    <i data-lucide="settings" className="w-4 h-4"></i>
    Paramètres
</button>
                                        <div className="border-t border-gray-100 my-1"></div>
                                        <a 
                                            href="logout.php"
                                            onClick={() => tracking.logEvent('LOGOUT_CLICKED')}
                                            data-track="menu_logout"
                                            className="w-full text-left px-4 py-2 hover:bg-red-50 flex items-center gap-3 text-red-600 block"
                                        >
                                            <i data-lucide="log-out" className="w-4 h-4"></i>
                                            Déconnexion
                                        </a>
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>
                </header>
            );

            // Navigation en bas (Bottom Navigation)
            const BottomNav = () => (
                <nav className="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 shadow-2xl z-50">
                    <div className="max-w-7xl mx-auto px-2">
                        <div className="flex justify-around items-center h-20">
                            {menuItems.map(item => (
                                <button
                                    key={item.id}
                                    onClick={() => {
                                        setActiveTab(item.id);
                                        tracking.logEvent('TAB_CHANGED', { tab: item.id });
                                    }}
                                    data-track={`menu_${item.id}`}
                                    className={`flex flex-col items-center justify-center gap-1 px-3 py-2 rounded-xl transition-all min-w-[70px] ${
                                        activeTab === item.id
                                            ? 'text-indigo-600'
                                            : 'text-gray-500 hover:text-gray-700'
                                    }`}
                                >
                                    <div className={`p-2 rounded-xl transition-all ${
                                        activeTab === item.id
                                            ? 'bg-gradient-to-r from-indigo-500 to-purple-600 shadow-lg'
                                            : 'bg-transparent'
                                    }`}>
                                        <i 
                                            data-lucide={item.icon} 
                                            className={`w-6 h-6 ${
                                                activeTab === item.id ? 'text-white' : ''
                                            }`}
                                        ></i>
                                    </div>
                                    <span className={`text-xs font-semibold ${
                                        activeTab === item.id ? 'text-indigo-600' : 'text-gray-600'
                                    }`}>
                                        {item.label}
                                    </span>
                                </button>
                            ))}
                        </div>
                    </div>
                </nav>
            );

            // ========== PAGE ACCUEIL ==========
            const AccueilPage = () => (
                <div className="min-h-screen bg-gradient-to-br from-indigo-50 via-purple-50 to-pink-50">
                    {/* Hero Section */}
                    <div className="wave-bg text-white py-20 px-4">
                        <div className="max-w-4xl mx-auto text-center">
                            <div className="animate-float mb-6">
                                <i data-lucide="heart" className="w-24 h-24 mx-auto"></i>
                            </div>
                            <h1 className="text-4xl md:text-6xl font-extrabold mb-6">
                                Bienvenue dans votre espace famille ! 🎉
                            </h1>
                            <p className="text-xl md:text-2xl text-indigo-100 mb-8">
                                Partagez, jouez et créez des souvenirs ensemble
                            </p>
                            <a href="https://docs.google.com/forms/d/e/1FAIpQLSdbu9kRewF2T7yc862IAwXx-PXVYhblg_gidxDIls1s4AoC5A/viewform?usp=publish-editor"><button 
                                onClick={() => tracking.logEvent('HERO_CTA_CLICKED')}
                                data-track="hero_start_adventure"
                                className="bg-white text-indigo-600 font-bold py-4 px-8 rounded-2xl shadow-2xl hover:shadow-3xl hover:scale-105 transition-all flex items-center gap-3 mx-auto"
                            >
                                <i data-lucide="rocket"></i>
                                Donnez-nous votre avis!
                            </button></a>
                        </div>
                    </div>

                    {/* Cartes principales */}
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div className="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                            {/* Carte Jeux */}
                            <div
                                onClick={() => {
                                    setActiveTab('jeux');
                                    tracking.logEvent('NAVIGATION_CARD_CLICKED', { destination: 'jeux' });
                                }}
                                data-track="card_jeux"
                                className="bg-white rounded-3xl shadow-lg hover:shadow-2xl transition-all cursor-pointer transform hover:-translate-y-2 p-6 border-2 border-transparent hover:border-blue-200"
                            >
                                <div className="bg-gradient-to-r from-blue-400 to-blue-600 w-16 h-16 rounded-2xl flex items-center justify-center mb-4">
                                    <i data-lucide="gamepad-2" className="w-8 h-8 text-white"></i>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-800 mb-2">Jeux</h3>
                                <p className="text-gray-600 mb-4">8 jeux amusants à découvrir ensemble</p>
                                <div className="flex items-center text-blue-600 font-semibold">
                                    Jouer maintenant
                                    <i data-lucide="arrow-right" className="w-4 h-4 ml-2"></i>
                                </div>
                            </div>

                            {/* Carte Quiz */}
                            <div
                                onClick={() => {
                                    setActiveTab('quiz');
                                    tracking.logEvent('NAVIGATION_CARD_CLICKED', { destination: 'quiz' });
                                }}
                                data-track="card_quiz"
                                className="bg-white rounded-3xl shadow-lg hover:shadow-2xl transition-all cursor-pointer transform hover:-translate-y-2 p-6 border-2 border-transparent hover:border-purple-200"
                            >
                                <div className="bg-gradient-to-r from-purple-400 to-purple-600 w-16 h-16 rounded-2xl flex items-center justify-center mb-4">
                                    <i data-lucide="help-circle" className="w-8 h-8 text-white"></i>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-800 mb-2">Quiz</h3>
                                <p className="text-gray-600 mb-4">Apprenez à mieux vous connaître</p>
                                <div className="flex items-center text-purple-600 font-semibold">
                                    Commencer
                                    <i data-lucide="arrow-right" className="w-4 h-4 ml-2"></i>
                                </div>
                            </div>

                            {/* Carte Activités */}
                            <div
                                onClick={() => {
                                    setActiveTab('activites');
                                    tracking.logEvent('NAVIGATION_CARD_CLICKED', { destination: 'activites' });
                                }}
                                data-track="card_activites"
                                className="bg-white rounded-3xl shadow-lg hover:shadow-2xl transition-all cursor-pointer transform hover:-translate-y-2 p-6 border-2 border-transparent hover:border-orange-200"
                            >
                                <div className="bg-gradient-to-r from-orange-400 to-pink-600 w-16 h-16 rounded-2xl flex items-center justify-center mb-4">
                                    <i data-lucide="sparkles" className="w-8 h-8 text-white"></i>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-800 mb-2">Activités</h3>
                                <p className="text-gray-600 mb-4">Des idées pour passer du temps ensemble</p>
                                <div className="flex items-center text-orange-600 font-semibold">
                                    Découvrir
                                    <i data-lucide="arrow-right" className="w-4 h-4 ml-2"></i>
                                </div>
                            </div>

                            {/* Carte Albums */}
                            <div
                                onClick={() => {
                                    setActiveTab('albums');
                                    tracking.logEvent('NAVIGATION_CARD_CLICKED', { destination: 'albums' });
                                }}
                                data-track="card_albums"
                                className="bg-white rounded-3xl shadow-lg hover:shadow-2xl transition-all cursor-pointer transform hover:-translate-y-2 p-6 border-2 border-transparent hover:border-pink-200"
                            >
                                <div className="bg-gradient-to-r from-pink-400 to-pink-600 w-16 h-16 rounded-2xl flex items-center justify-center mb-4">
                                    <i data-lucide="images" className="w-8 h-8 text-white"></i>
                                </div>
                                <h3 className="text-2xl font-bold text-gray-800 mb-2">Albums</h3>
                                <p className="text-gray-600 mb-4">Vos souvenirs en photos et vidéos</p>
                                <div className="flex items-center text-pink-600 font-semibold">
                                    Voir les albums
                                    <i data-lucide="arrow-right" className="w-4 h-4 ml-2"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Section Statistiques */}
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                        <div className="bg-white rounded-3xl shadow-xl p-8">
                            <h2 className="text-3xl font-bold text-gray-800 mb-8 text-center">
                                Votre famille en chiffres ✨
                            </h2>
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
                                <div className="text-center">
                                    <div className="bg-indigo-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="trophy" className="w-8 h-8 text-indigo-600"></i>
                                    </div>
                                    <p className="text-3xl font-bold text-indigo-600 mb-1">24</p>
                                    <p className="text-sm text-gray-600">Jeux joués</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-purple-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="check-circle" className="w-8 h-8 text-purple-600"></i>
                                    </div>
                                    <p className="text-3xl font-bold text-purple-600 mb-1">12</p>
                                    <p className="text-sm text-gray-600">Quiz complétés</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-orange-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="star" className="w-8 h-8 text-orange-600"></i>
                                    </div>
                                    <p className="text-3xl font-bold text-orange-600 mb-1">8</p>
                                    <p className="text-sm text-gray-600">Activités faites</p>
                                </div>
                                <div className="text-center">
                                    <div className="bg-pink-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3">
                                        <i data-lucide="camera" className="w-8 h-8 text-pink-600"></i>
                                    </div>
                                    <p className="text-3xl font-bold text-pink-600 mb-1">156</p>
                                    <p className="text-sm text-gray-600">Photos partagées</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );

            // ========== PAGE ALBUMS ==========
            // ========== PAGE ALBUMS ==========
const AlbumsPage = () => {
    const [albums, setAlbums] = useState({});
    const [currentAlbum, setCurrentAlbum] = useState(null);
    const [newAlbum, setNewAlbum] = useState("");
    const [loading, setLoading] = useState(true);
    const [lightboxPhoto, setLightboxPhoto] = useState(null);

    // Charger les albums depuis la base de données
    useEffect(() => {
        loadAlbumsFromDB();
    }, []);

    const loadAlbumsFromDB = async () => {
    try {
        const response = await fetch('api/load_shared_data.php?type=albums');
        const data = await response.json();
        if(data.success) {
            // Convertir en format simple pour l'affichage
            const simpleAlbums = {};
            Object.keys(data.albums).forEach(albumName => {
                simpleAlbums[albumName] = data.albums[albumName].photos;
            });
            setAlbums(simpleAlbums);
        }
    } catch(error) {
        console.error('Erreur chargement albums:', error);
    } finally {
        setLoading(false);
    }
};

    useEffect(() => {
        lucide.createIcons();
    }, [albums, currentAlbum]);

    const createAlbum = async () => {
        if (!newAlbum.trim()) return;
        if (albums[newAlbum]) {
            alert("Un album avec ce nom existe déjà.");
            return;
        }

        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'album',
                    album_name: newAlbum
                })
            });
            const data = await response.json();
            
            if(data.success) {
                setAlbums({ ...albums, [newAlbum]: [] });
                setNewAlbum("");
                tracking.logEvent('ALBUM_CREATED', { albumName: newAlbum });
            }
        } catch(error) {
            console.error('Erreur création album:', error);
            alert('Erreur lors de la création de l\'album');
        }
    };

    const deleteAlbum = async (name) => {
        if (!confirm(`Supprimer l'album "${name}" ?`)) return;

        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'delete_album',
                    album_name: name
                })
            });
            const data = await response.json();
            
            if(data.success) {
                const copy = { ...albums };
                delete copy[name];
                setAlbums(copy);
                setCurrentAlbum(null);
                tracking.logEvent('ALBUM_DELETED', { albumName: name });
            }
        } catch(error) {
            console.error('Erreur suppression album:', error);
        }
    };

    const renameAlbum = async (oldName) => {
        const newName = prompt("Nouveau nom de l'album :", oldName);
        if (!newName || !newName.trim()) return;

        if (albums[newName] && newName !== oldName) {
            alert("Un album avec ce nom existe déjà.");
            return;
        }

        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'rename_album',
                    old_name: oldName,
                    new_name: newName
                })
            });
            const data = await response.json();
            
            if(data.success) {
                const updated = { ...albums };
                updated[newName] = updated[oldName];
                delete updated[oldName];
                setAlbums(updated);
                setCurrentAlbum(newName);
                tracking.logEvent('ALBUM_RENAMED', { oldName, newName });
            }
        } catch(error) {
            console.error('Erreur renommage album:', error);
        }
    };

    const addPhoto = (e) => {
    const file = e.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = async () => {
        try {
            // Compresser l'image via un canvas
            const img = new Image();
            img.onload = async () => {
                const canvas = document.createElement('canvas');
                const MAX_SIZE = 1200;
                let width = img.width;
                let height = img.height;

                if (width > height) {
                    if (width > MAX_SIZE) { height *= MAX_SIZE / width; width = MAX_SIZE; }
                } else {
                    if (height > MAX_SIZE) { width *= MAX_SIZE / height; height = MAX_SIZE; }
                }

                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0, width, height);
                
                // Qualité 0.7 = bon compromis taille/qualité
                const compressedData = canvas.toDataURL('image/jpeg', 0.7);

                const response = await fetch('api/save_data.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        type: 'photo',
                        album_name: currentAlbum,
                        photo_name: file.name,
                        photo_data: compressedData
                    })
                });
                const data = await response.json();
                
                if(data.success) {
                    await loadAlbumsFromDB();
                    tracking.logEvent('PHOTO_ADDED', { albumName: currentAlbum, photoName: file.name });
                } else {
                    alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                }
            };
            img.src = reader.result;
        } catch(error) {
            console.error('Erreur ajout photo:', error);
            alert('Erreur: ' + error.message);
        }
    };
    reader.readAsDataURL(file);
};

    const deletePhoto = async (index) => {
        if (!confirm("Supprimer cette photo ?")) return;

        const photo = albums[currentAlbum][index];
        
        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'delete_photo',
                    album_name: currentAlbum,
                    photo_name: photo.name
                })
            });
            const data = await response.json();
            
            if(data.success) {
                const updated = [...albums[currentAlbum]];
                updated.splice(index, 1);
                setAlbums({ ...albums, [currentAlbum]: updated });
                tracking.logEvent('PHOTO_DELETED', { albumName: currentAlbum });
            }
        } catch(error) {
            console.error('Erreur suppression photo:', error);
        }
    };
    
    const Lightbox = () => {
    if (!lightboxPhoto) return null;
    const total = albums[currentAlbum].length;

    const goNext = (e) => {
        e.stopPropagation();
        const nextIndex = (lightboxPhoto.index + 1) % total;
        setLightboxPhoto({ ...albums[currentAlbum][nextIndex], index: nextIndex });
    };
    const goPrev = (e) => {
        e.stopPropagation();
        const prevIndex = (lightboxPhoto.index - 1 + total) % total;
        setLightboxPhoto({ ...albums[currentAlbum][prevIndex], index: prevIndex });
    };

    return (
        <div
            className="fixed inset-0 bg-black bg-opacity-95 z-50 flex items-center justify-center p-4"
            onClick={() => setLightboxPhoto(null)}
        >
        
        
            {/* Fermer */}
            <button
                onClick={() => setLightboxPhoto(null)}
                className="absolute top-4 right-4 text-white bg-white bg-opacity-20 hover:bg-opacity-40 rounded-full w-12 h-12 flex items-center justify-center transition-all text-2xl font-bold z-10"
            >
                ✕
            </button>

            {/* Compteur */}
            <div className="absolute top-4 left-1/2 -translate-x-1/2 text-white text-sm font-semibold bg-black bg-opacity-40 px-4 py-2 rounded-full">
                {lightboxPhoto.index + 1} / {total}
            </div>

            {/* Flèche gauche */}
            {total > 1 && (
                <button
                    onClick={goPrev}
                    className="absolute left-4 text-white bg-white bg-opacity-20 hover:bg-opacity-40 rounded-full w-12 h-12 flex items-center justify-center transition-all z-10 text-xl"
                >
                    ‹
                </button>
            )}

            {/* Image */}
            <img
                src={lightboxPhoto.data}
                alt={lightboxPhoto.name}
                className="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl"
                onClick={(e) => e.stopPropagation()}
            />

            {/* Flèche droite */}
            {total > 1 && (
                <button
                    onClick={goNext}
                    className="absolute right-4 text-white bg-white bg-opacity-20 hover:bg-opacity-40 rounded-full w-12 h-12 flex items-center justify-center transition-all z-10 text-xl"
                >
                    ›
                </button>
            )}

            {/* Nom de la photo */}
            <div className="absolute bottom-4 left-1/2 -translate-x-1/2 text-white text-sm bg-black bg-opacity-40 px-4 py-2 rounded-full max-w-[80%] truncate text-center">
                {lightboxPhoto.name}
            </div>
        </div>
    );
};

    if(loading) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100 flex items-center justify-center">
                <div className="text-center">
                    <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
                    <p className="text-gray-600">Chargement des albums...</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100 p-6">
        <Lightbox />
            <div className="max-w-6xl mx-auto">
                <div className="flex justify-between items-center mb-10">
                    <button
                        onClick={() => {
                            setActiveTab('accueil');
                            tracking.logEvent('ALBUMS_BACK_CLICKED');
                        }}
                        data-track="albums_back_button"
                        className="flex items-center gap-2 text-gray-600 font-bold hover:text-purple-600 transition-colors"
                    >
                        <i data-lucide="arrow-left"></i> Retour
                    </button>

                    <h1 className="text-4xl md:text-5xl font-extrabold text-center text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600">
                        Vos albums
                    </h1>

                    <div className="w-24"></div>
                </div>

                {!currentAlbum ? (
                    <>
                        <div className="flex flex-col md:flex-row gap-4 justify-center mb-10">
                            <input
                                value={newAlbum}
                                onChange={(e) => setNewAlbum(e.target.value)}
                                placeholder="Nom du nouvel album"
                                className="px-5 py-3 rounded-2xl border-2 border-purple-200 focus:outline-none focus:border-purple-400"
                            />
                            <button
                                onClick={createAlbum}
                                data-track="album_create"
                                className="bg-gradient-to-r from-purple-500 to-pink-500 text-white font-bold px-6 py-3 rounded-2xl shadow-lg hover:scale-105 transition"
                            >
                                Créer un album
                            </button>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-3 gap-6">
                            {Object.keys(albums).map(album => (
                                <div
                                    key={album}
                                    className="relative bg-white rounded-3xl shadow-xl p-6 hover:scale-105 transition"
                                >
                                    <h2
                                        onClick={() => {
                                            setCurrentAlbum(album);
                                            tracking.logEvent('ALBUM_OPENED', { albumName: album });
                                        }}
                                        data-track={`album_open_${album}`}
                                        className="text-2xl font-bold text-gray-800 cursor-pointer mb-2"
                                    >
                                        {album}
                                    </h2>
                                    <p className="text-gray-500">
                                        {albums[album].length} photo{albums[album].length > 1 ? 's' : ''}
                                    </p>

                                    <button
                                        onClick={() => deleteAlbum(album)}
                                        data-track={`album_delete_${album}`}
                                        className="absolute top-4 right-4 text-red-400 hover:text-red-600 font-bold"
                                    >
                                        ✕
                                    </button>
                                </div>
                            ))}
                        </div>

                        {Object.keys(albums).length === 0 && (
                            <div className="bg-white rounded-3xl shadow-xl p-12 text-center">
                                <i data-lucide="image-plus" className="w-24 h-24 mx-auto text-purple-300 mb-6"></i>
                                <h3 className="text-2xl font-bold text-gray-800 mb-4">
                                    Aucun album pour le moment
                                </h3>
                                <p className="text-gray-600 text-lg">
                                    Créez votre premier album pour commencer à sauvegarder vos souvenirs ! 📸
                                </p>
                            </div>
                        )}
                    </>
                ) : (
                    <>
                        <div className="flex flex-wrap justify-between items-center mb-6 gap-4">
                            <button
                                onClick={() => {
                                    setCurrentAlbum(null);
                                    tracking.logEvent('ALBUM_CLOSED', { albumName: currentAlbum });
                                }}
                                data-track="album_back_to_list"
                                className="text-purple-600 font-bold flex items-center gap-2"
                            >
                                <i data-lucide="arrow-left"></i>
                                Retour aux albums
                            </button>

                            <div className="flex gap-4">
                                <button
                                    onClick={() => renameAlbum(currentAlbum)}
                                    data-track="album_rename"
                                    className="text-blue-500 font-bold flex items-center gap-2"
                                >
                                    <i data-lucide="edit-2" className="w-4 h-4"></i>
                                    Renommer
                                </button>

                                <button
                                    onClick={() => deleteAlbum(currentAlbum)}
                                    data-track="album_delete_current"
                                    className="text-red-500 font-bold flex items-center gap-2"
                                >
                                    <i data-lucide="trash-2" className="w-4 h-4"></i>
                                    Supprimer
                                </button>
                            </div>
                        </div>

                        <h2 className="text-3xl font-bold text-gray-800 mb-6 flex items-center gap-3">
                            <i data-lucide="folder" className="text-purple-500"></i>
                            {currentAlbum}
                        </h2>

                        <div className="mb-8 bg-white p-6 rounded-2xl shadow-lg">
                            <label className="flex items-center gap-3 cursor-pointer group">
                                <div className="bg-gradient-to-r from-purple-500 to-pink-500 p-3 rounded-xl group-hover:scale-105 transition">
                                    <i data-lucide="upload" className="w-6 h-6 text-white"></i>
                                </div>
                                <div>
                                    <p className="font-bold text-gray-800">Ajouter une photo</p>
                                    <p className="text-sm text-gray-500">Cliquez pour sélectionner</p>
                                </div>
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={addPhoto}
                                    data-track="photo_upload"
                                    className="hidden"
                                />
                            </label>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-6">
    {albums[currentAlbum].map((img, i) => (
        <div
            key={i}
            className="relative group rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition cursor-pointer"
            onClick={() => setLightboxPhoto({ ...img, index: i })}
        >
            <img
                src={img.data}
                alt={img.name}
                className="object-cover w-full h-48 group-hover:scale-105 transition-transform duration-300"
            />
            <div className="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-20 transition-all flex items-center justify-center">
                <i data-lucide="zoom-in" className="w-8 h-8 text-white opacity-0 group-hover:opacity-100 transition-opacity"></i>
            </div>
            <button
                onClick={(e) => { e.stopPropagation(); deletePhoto(i); }}
                data-track="photo_delete"
                className="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 opacity-0 group-hover:opacity-100 transition font-bold flex items-center justify-center"
            >
                ✕
            </button>
        </div>
    ))}
</div>

                        {albums[currentAlbum].length === 0 && (
                            <div className="bg-white rounded-2xl shadow-lg p-12 text-center">
                                <i data-lucide="image" className="w-20 h-20 mx-auto text-purple-300 mb-4"></i>
                                <p className="text-gray-600 text-lg">
                                    Cet album est vide. Ajoutez votre première photo ! 📷
                                </p>
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
};

            // ========== PAGE ACTIVITÉS ==========
            const ActivitesPage = () => {
            	const [screen, setScreen] = useState('list');

            
                const [showTracking, setShowTracking] = useState(false);
                const [showAdmin, setShowAdmin] = useState(false);
                const [isAdmin, setIsAdmin] = useState(false);
                const ADMIN_PASSWORD = 'admin123';

                const [adminActivities] = useState(() => {
                    const stored = localStorage.getItem("admin_activities");
                    if (stored) {
                        return JSON.parse(stored);
                    }
                    return [
                        {
                            title: "Recette de gâteau",
                            steps: [
                                "ing",
                            ],
                            icon: "cake"
                        },
                        {
                            title: "Idées de films en famille",
                            steps: [
                                "Rental family",
                                
                            ],
                            icon: "film"
                        },
                        {
                            title: "Spectacle de marionnettes",
                            steps: [
                                "Récupérer des vieilles chaussettes et des boîtes en carton",
                                "Inventer une histoire",
                                "Créer une scène avec le carton",
                                "Attribuer les rôles et répéter le spectacle"
                            ],
                            icon: "home"
                        },
                        {
                            title: "Idées de jeux de société",
                            steps: [
                                "Jeux de 52 cartes",
                                "Pictionary",
                                "Trivial Pursuit",
                                "Monopoly"
                            ],
                            icon: "puzzle"
                        },
                        {
                            title: "Faire un herbier",
                            steps: [
                                "Sortir dehors",
                                "Récupérer toutes sortes de plantes ou fleurs tombée ou morte",
                                "Prendre un cahier vide ou inutilisé",
                                "Coller les plantes à l'intérieur",
                                "Ecraser le cahier pour que les plantes soient plates"
                            ],
                            icon: "leaf"
                        },
                        {
                            title: "Faire un coloriage",
                            steps: [
                                "Imprimer un coloriage",
                                "Le diviser en deux (en découpant ou en traçant un trait)",
                                "Colorier chacun de son côté, de préférence sans que l'autre voit",
                                "Regarder le résultat"
                            ],
                            icon: "pencil"
                        },
                        {
                            title: "Dessiner chacun un portrait de l'autre",
                            steps: [
                                "Préparer le matériel (papier, crayons, feutres)",
                                "S'installer face à face",
                                "Dessiner pendant 10-15 minutes",
                                "Échanger les portraits",
                                "Rire ensemble des résultats",
                                "Afficher les œuvres sur le frigo"
                            ],
                            icon: "palette"
                        }
                    ];
                });

                const [selectedActivity, setSelectedActivity] = useState(null);

                useEffect(() => {
                    lucide.createIcons();
                }, [selectedActivity, showTracking, showAdmin]);

                const handleActivityClick = (activity) => {
                    setSelectedActivity(activity);
                    tracking.logEvent('ACTIVITY_OPENED', { activity: activity.title });
                };

                const closeActivityDetail = () => {
                    tracking.logEvent('ACTIVITY_CLOSED', { activity: selectedActivity?.title });
                    setSelectedActivity(null);
                };
                

                
                const WelcomeActivitiesScreen = () => (
    <div className="min-h-screen bg-gradient-to-br from-yellow-50 via-orange-50 to-pink-50 flex items-center justify-center p-4">
        <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">

            <i data-lucide="sparkles" className="w-24 h-24 mx-auto text-orange-500 mb-6"></i>

            <h1 className="text-3xl font-extrabold text-gray-800 mb-4">
                Activités Ensemble 🎉
            </h1>

            <p className="text-gray-600 mb-8">
                Découvrez des idées amusantes à faire en famille
            </p>

            <button
                onClick={() => {
                    tracking.logEvent('SCREEN_CHANGE', {
                        from: 'welcome_activities',
                        to: 'activities_list'
                    });

                    setScreen('list');
                }}
                data-track="activities_explore_button"
                className="w-full bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold py-4 rounded-xl mb-3"
            >
                Explorer les activités
            </button>

            <button
                onClick={() => {
                    tracking.logEvent('ADMIN_OPENED');
                    setShowAdmin(true);
                }}
                data-track="activities_admin_button"
                className="w-full bg-gray-100 text-gray-600 font-medium py-3 rounded-xl"
            >
                Administration
            </button>

        </div>
    </div>
);


                // Vue détaillée de l'activité
                const ActivityDetailView = ({ activity }) => {
                    useEffect(() => {
                        lucide.createIcons();
                    }, []);

                    return (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                            <div className="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                                <div className="bg-gradient-to-r from-orange-500 to-pink-500 p-6 text-white">
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-center gap-4">
                                            <div className="bg-white bg-opacity-20 p-3 rounded-xl">
                                                <i data-lucide={activity.icon || "star"} className="w-8 h-8"></i>
                                            </div>
                                            <div>
                                                <h2 className="text-2xl font-bold">{activity.title}</h2>
                                                <p className="text-orange-100 mt-1">{activity.steps.length} étapes</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={closeActivityDetail}
                                            data-track="activity_detail_close"
                                            className="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors"
                                        >
                                            <i data-lucide="x" className="w-6 h-6"></i>
                                        </button>
                                    </div>
                                </div>

                                <div className="p-6 overflow-y-auto max-h-[calc(90vh-200px)]">
                                    <h3 className="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                        <i data-lucide="list-checks" className="text-orange-500"></i>
                                        Étapes à suivre
                                    </h3>

                                    <div className="space-y-3">
                                        {activity.steps.map((step, index) => (
                                            <div
                                                key={index}
                                                className="flex gap-4 items-start bg-gradient-to-r from-orange-50 to-pink-50 p-4 rounded-xl hover:shadow-md transition-all"
                                            >
                                                <div className="flex-shrink-0 bg-orange-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold">
                                                    {index + 1}
                                                </div>
                                                <p className="text-gray-800 font-medium pt-1">{step}</p>
                                            </div>
                                        ))}
                                    </div>
                                </div>

                                <div className="p-6 bg-gray-50 border-t">
                                    <button
                                        onClick={() => {
                                            tracking.logEvent('ACTIVITY_STARTED', { activity: activity.title });
                                            closeActivityDetail();
                                        }}
                                        data-track="activity_start"
                                        className="w-full bg-gradient-to-r from-orange-500 to-pink-500 hover:from-orange-600 hover:to-pink-600 text-white font-bold py-4 px-6 rounded-xl transition-all flex items-center justify-center gap-2"
                                    >
                                        <i data-lucide="check-circle"></i>
                                        C'est parti !
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                };

                // Panneau de statistiques de tracking
                const TrackingPanel = () => {
                    const [stats, setStats] = useState(tracking.getStats());

                    useEffect(() => {
                        lucide.createIcons();
                    }, []);

                    const refreshStats = () => {
                        setStats(tracking.getStats());
                        tracking.logEvent('STATS_REFRESHED');
                    };

                    const clearData = () => {
                        if (confirm('Êtes-vous sûr de vouloir effacer toutes les données de tracking ?')) {
                            tracking.clearHistory();
                            setStats(tracking.getStats());
                            tracking.logEvent('TRACKING_DATA_CLEARED');
                        }
                    };

                    return (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                            <div className="bg-white rounded-3xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
                                <div className="bg-gradient-to-r from-blue-600 to-purple-600 p-6 text-white">
                                    <div className="flex justify-between items-center">
                                        <h2 className="text-2xl font-bold flex items-center gap-3">
                                            <i data-lucide="bar-chart" className="w-8 h-8"></i>
                                            Tableau de Tracking
                                        </h2>
                                        <button
                                            onClick={() => {
                                                tracking.logEvent('BUTTON_CLICK', { button: 'close_tracking_panel' });
                                                setShowTracking(false);
                                            }}
                                            data-track="tracking_panel_close"
                                            className="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors"
                                        >
                                            <i data-lucide="x" className="w-6 h-6"></i>
                                        </button>
                                    </div>
                                </div>

                                <div className="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                        <div className="bg-blue-50 p-4 rounded-xl">
                                            <p className="text-sm text-gray-600 font-semibold">Total d'événements</p>
                                            <p className="text-3xl font-bold text-blue-600">{stats.totalEvents}</p>
                                        </div>
                                        <div className="bg-green-50 p-4 rounded-xl">
                                            <p className="text-sm text-gray-600 font-semibold">Types d'événements</p>
                                            <p className="text-3xl font-bold text-green-600">{Object.keys(stats.byType).length}</p>
                                        </div>
                                        <div className="bg-purple-50 p-4 rounded-xl">
                                            <p className="text-sm text-gray-600 font-semibold">Sessions</p>
                                            <p className="text-3xl font-bold text-purple-600">{Object.keys(stats.bySessions).length}</p>
                                        </div>
                                    </div>

                                    <div className="bg-gray-50 p-6 rounded-2xl mb-6">
                                        <h3 className="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                            <i data-lucide="pie-chart"></i>
                                            Événements par type
                                        </h3>
                                        <div className="space-y-3">
                                            {Object.entries(stats.byType).map(([type, count]) => (
                                                <div key={type} className="flex items-center justify-between bg-white p-3 rounded-xl">
                                                    <span className="font-medium text-gray-700">{type}</span>
                                                    <span className="bg-blue-100 text-blue-700 px-3 py-1 rounded-full font-bold">{count}</span>
                                                </div>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="flex gap-3">
                                        <button
                                            onClick={refreshStats}
                                            data-track="tracking_refresh"
                                            className="flex-1 bg-blue-500 text-white px-4 py-3 rounded-xl font-bold hover:bg-blue-600 transition-colors flex items-center justify-center gap-2"
                                        >
                                            <i data-lucide="refresh-cw"></i>
                                            Rafraîchir
                                        </button>
                                        <button
                                            onClick={() => {
                                                tracking.logEvent('BUTTON_CLICK', { button: 'export_csv' });
                                                tracking.exportToCSV();
                                            }}
                                            data-track="tracking_export_csv"
                                            className="flex-1 bg-green-500 text-white px-4 py-3 rounded-xl font-bold hover:bg-green-600 transition-colors flex items-center justify-center gap-2"
                                        >
                                            <i data-lucide="download"></i>
                                            Exporter CSV
                                        </button>
                                        <button
                                            onClick={clearData}
                                            data-track="tracking_clear_all"
                                            className="flex-1 bg-red-500 text-white px-4 py-3 rounded-xl font-bold hover:bg-red-600 transition-colors flex items-center justify-center gap-2"
                                        >
                                            <i data-lucide="trash-2"></i>
                                            Effacer tout
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                };

                // Panneau d'administration (simplifié)
                const AdminPanel = () => {
                    const [activities, setActivities] = useState(() => {
                        const stored = localStorage.getItem("admin_activities");
                        return stored ? JSON.parse(stored) : adminActivities;
                    });

                    useEffect(() => {
                        lucide.createIcons();
                    }, [activities]);

                    return (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                            <div className="bg-white rounded-3xl shadow-2xl max-w-3xl w-full p-8">
                                <div className="flex justify-between items-center mb-6">
                                    <h2 className="text-2xl font-bold text-gray-800">Panneau Admin</h2>
                                    <button
                                        onClick={() => setShowAdmin(false)}
                                        data-track="admin_panel_close"
                                        className="text-gray-400 hover:text-gray-600"
                                    >
                                        <i data-lucide="x" className="w-6 h-6"></i>
                                    </button>
                                </div>
                                <p className="text-gray-600">Gestion des activités (panneau complet à venir)</p>
                            </div>
                        </div>
                    );
                };

                return (
                    <div className="min-h-screen bg-gradient-to-br from-yellow-50 to-orange-50 p-6">
                        {selectedActivity && <ActivityDetailView activity={selectedActivity} />}
                        {showTracking && <TrackingPanel />}
                        {showAdmin && <AdminPanel />}
                        
                        <div className="max-w-3xl mx-auto">
                            <div className="flex justify-between items-center mb-6">
                                <button
    onClick={() => {
        tracking.logEvent('BUTTON_CLICK', {
            button: 'retour_activities',
            from_screen: 'activities_list',
            to_screen: 'welcome_activities'
        });

        setScreen('welcome');
    }}
    data-track="activities_back_button"
    className="flex items-center gap-2 text-gray-600 font-bold hover:text-orange-600 transition-colors"
>
    <i data-lucide="arrow-left"></i> Retour
</button>



                                <button
                                    onClick={() => setShowTracking(!showTracking)}
                                    data-track="activities_stats_button"
                                    className="flex items-center gap-2 text-blue-600 font-bold hover:text-blue-700 transition-colors px-4 py-2 bg-blue-50 rounded-xl"
                                >
                                    <i data-lucide="bar-chart"></i> Statistiques
                                </button>
                            </div>

                            <h1 className="text-4xl font-extrabold text-gray-800 mb-6 text-center">
                                Activités à faire ensemble 🎉
                            </h1>

                            <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl mb-8">
                                <div className="flex items-start gap-3">
                                    <i data-lucide="info" className="text-blue-500 w-5 h-5 mt-1"></i>
                                    <div>
                                        <p className="text-blue-800 font-medium">
                                            Cliquez sur une activité pour voir les étapes à suivre !
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div className="grid md:grid-cols-2 gap-4">
                                {adminActivities.map((activity, index) => (
                                    <div
                                        key={index}
                                        onClick={() => handleActivityClick(activity)}
                                        data-track={`activity_card_${activity.title.toLowerCase().replace(/ /g, '_')}`}
                                        className="bg-white p-6 rounded-2xl shadow-md border-l-8 border-orange-400 hover:shadow-xl transition-all transform hover:-translate-y-2 cursor-pointer group"
                                    >
                                        <div className="flex items-start gap-4">
                                            <div className="bg-orange-100 p-3 rounded-xl group-hover:bg-orange-200 transition-colors">
                                                <i data-lucide={activity.icon || "star"} className="text-orange-500 w-8 h-8"></i>
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="text-xl font-bold text-gray-800 mb-2 group-hover:text-orange-600 transition-colors">
                                                    {activity.title}
                                                </h3>
                                                <p className="text-gray-600 text-sm flex items-center gap-2">
                                                    <i data-lucide="list" className="w-4 h-4"></i>
                                                    {activity.steps.length} étapes
                                                </p>
                                            </div>
                                            <div className="text-orange-400 group-hover:text-orange-600 transition-colors">
                                                <i data-lucide="chevron-right" className="w-6 h-6"></i>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>

                            {adminActivities.length > 0 && (
                                <div className="mt-8 bg-gradient-to-r from-orange-100 to-pink-100 p-6 rounded-2xl text-center">
                                    <p className="text-2xl font-bold text-gray-800">
                                        {adminActivities.length} activité{adminActivities.length > 1 ? 's' : ''} géniale{adminActivities.length > 1 ? 's' : ''} à découvrir ! 🌟
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                );
                
                
            };

            // ========== PAGE JEUX ==========
            const JeuxPage = () => {
                const [screen, setScreen] = useState('games');
                const [activeGame, setActiveGame] = useState(null);

                const [selectedGame, setSelectedGame] = useState(null);
                const [filterDifficulty, setFilterDifficulty] = useState('all');

                useEffect(() => {
                    lucide.createIcons();
                }, [screen, selectedGame, filterDifficulty, activeGame]);

                // --- DONNÉES DES JEUX ---
                const GAMES = [
                    {
                        id: 'morpion',
                        title: 'Morpion',
                        description: 'Le classique jeu de stratégie à deux joueurs',
                        icon: 'grid-3x3',
                        difficulty: 'Facile',
                        players: '2 joueurs',
                        duration: '5 min',
                        color: 'blue',
                        status: 'available'
                    },
                    {
                        id: 'baccalaureat',
                        title: 'Baccalauréat',
                        description: 'Trouvez des mots pour chaque catégorie',
                        icon: 'book-open',
                        difficulty: 'Moyen',
                        players: '2+ joueurs',
                        duration: '15 min',
                        color: 'green',
                        status: 'available'
                    },
                    {
                        id: 'ile-interdite',
                        title: 'L\'île interdite',
                        description: 'Coopérez pour récupérer les trésors avant que l\'île ne coule',
                        icon: 'island',
                        difficulty: 'Moyen',
                        players: '2-4 joueurs',
                        duration: '30 min',
                        color: 'teal',
                        status: 'soon'
                    },
                    {
                        id: 'pendu',
                        title: 'Le Pendu',
                        description: 'Devinez le mot lettre par lettre',
                        icon: 'spellcheck',
                        difficulty: 'Facile',
                        players: '2 joueurs',
                        duration: '5 min',
                        color: 'purple',
                        status: 'available'
                    },
                    {
                        id: 'pictionary',
                        title: 'Pictionary',
                        description: 'Dessinez et faites deviner les mots',
                        icon: 'palette',
                        difficulty: 'Facile',
                        players: '2+ joueurs',
                        duration: '10 min',
                        color: 'orange',
                        status: 'available'
                    },
                    {
                        id: 'memory',
                        title: 'Memory',
                        description: 'Retrouvez les paires de cartes',
                        icon: 'brain',
                        difficulty: 'Moyen',
                        players: '2+ joueurs',
                        duration: '10 min',
                        color: 'pink',
                        status: 'available'
                    },
                    {
                        id: 'puissance4',
                        title: 'Puissance 4',
                        description: 'Aligne 4 jetons avant ton adversaire !',
                        icon: 'circle',
                        difficulty: 'Facile',
                        players: '2 joueurs',
                        duration: '10 min',
                        color: 'yellow',
                        status: 'available'
                    },
                    {
                        id: 'jeu2',
                        title: '2',
                        description: 'Jeu mystère à découvrir bientôt',
                        icon: 'sparkles',
                        difficulty: 'Moyen',
                        players: '2+ joueurs',
                        duration: '15 min',
                        color: 'indigo',
                        status: 'soon'
                    }
                ];

                // Écran d'accueil des jeux
                const WelcomeScreen = () => (
                    <div className="min-h-screen bg-gradient-to-br from-yellow-50 via-orange-50 to-pink-50 flex items-center justify-center p-4">
                        <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center transform transition-all hover:scale-105 duration-500">
                            <i data-lucide="gamepad-2" className="w-24 h-24 mx-auto text-orange-500 animate-bounce-slow mb-6"></i>
                            <h1 className="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-orange-600 to-pink-600 mb-4">
                                Jeux en Famille 🎮
                            </h1>
                            <p className="text-gray-600 mb-8 text-lg font-medium">
                                Jouez ensemble et créez des souvenirs inoubliables !
                            </p>
                            <button
                                onClick={() => {
                                    setScreen('games');
                                    tracking.logEvent('GAMES_DISCOVER_CLICKED');
                                }}
                                data-track="games_discover_button"
                                className="w-full bg-gradient-to-r from-orange-500 to-pink-500 hover:from-orange-600 hover:to-pink-600 text-white font-bold py-5 px-8 rounded-2xl shadow-lg transition-all flex items-center justify-center gap-3 text-xl mb-4"
                            >
                                Découvrir les jeux <i data-lucide="arrow-right"></i>
                            </button>
                            
                            <button
                                onClick={() => {
                                    setActiveTab('accueil');
                                    tracking.logEvent('GAMES_BACK_TO_HOME');
                                }}
                                data-track="games_back_home"
                                className="w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-3 px-6 rounded-xl transition-all flex items-center justify-center gap-2"
                            >
                                <i data-lucide="arrow-left"></i>
                                Retour à l'accueil
                            </button>
                            
                            <div className="mt-6 p-4 bg-orange-50 rounded-xl">
                                <p className="text-sm text-orange-800 font-medium flex items-center justify-center gap-2">
                                    <i data-lucide="info" className="w-4 h-4"></i>
                                    {GAMES.length} jeux disponibles
                                </p>
                            </div>
                        </div>
                    </div>
                );

                // Écran de sélection des jeux
                const GamesScreen = () => {
                    const filteredGames = filterDifficulty === 'all' 
                        ? GAMES 
                        : GAMES.filter(game => game.difficulty === filterDifficulty);

                    return (
                        <div className="min-h-screen bg-gradient-to-br from-yellow-50 to-orange-50 p-6">
                            <div className="max-w-6xl mx-auto">
                                {/* Header */}
                                <div className="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4">
                                    <button
                                    onClick={() => {
                                        setActiveTab('accueil');
                                        tracking.logEvent('GAMES_BACK_CLICKED');
                                    }}
                                    data-track="games_back_button"
                                    className="flex items-center gap-2 text-gray-600 font-bold hover:text-orange-600 transition-colors"
                                >
                                    <i data-lucide="arrow-left"></i> Retour
                                </button>

                                    <h1 className="text-4xl font-extrabold text-gray-800">
                                        Choisis ton jeu ! 🎯
                                    </h1>

                                    <div className="flex items-center gap-2 text-gray-600">
                                        <i data-lucide="trophy" className="text-orange-500"></i>
                                        <span className="font-semibold">{filteredGames.length} jeux</span>
                                    </div>
                                </div>

                                {/* Filtres */}
                                <div className="bg-white rounded-2xl shadow-md p-6 mb-8">
                                    <div className="flex items-center gap-3 mb-4">
                                        <i data-lucide="filter" className="text-orange-500"></i>
                                        <h3 className="text-lg font-bold text-gray-800">Filtrer par difficulté</h3>
                                    </div>
                                    
                                    <div className="flex flex-wrap gap-3">
                                        <button
                                            onClick={() => {
                                                setFilterDifficulty('all');
                                                tracking.logEvent('GAME_FILTER_CHANGED', { filter: 'all' });
                                            }}
                                            data-track="filter_all"
                                            className={`px-6 py-2 rounded-xl font-semibold transition-all ${
                                                filterDifficulty === 'all'
                                                    ? 'bg-orange-500 text-white shadow-lg'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            Tous
                                        </button>
                                        <button
                                            onClick={() => {
                                                setFilterDifficulty('Facile');
                                                tracking.logEvent('GAME_FILTER_CHANGED', { filter: 'facile' });
                                            }}
                                            data-track="filter_facile"
                                            className={`px-6 py-2 rounded-xl font-semibold transition-all ${
                                                filterDifficulty === 'Facile'
                                                    ? 'bg-green-500 text-white shadow-lg'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            Facile
                                        </button>
                                        <button
                                            onClick={() => {
                                                setFilterDifficulty('Moyen');
                                                tracking.logEvent('GAME_FILTER_CHANGED', { filter: 'moyen' });
                                            }}
                                            data-track="filter_moyen"
                                            className={`px-6 py-2 rounded-xl font-semibold transition-all ${
                                                filterDifficulty === 'Moyen'
                                                    ? 'bg-yellow-500 text-white shadow-lg'
                                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                                            }`}
                                        >
                                            Moyen
                                        </button>
                                    </div>
                                </div>

                                {/* Info banner */}
                                <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl mb-8">
                                    <div className="flex items-start gap-3">
                                        <i data-lucide="sparkles" className="text-blue-500 w-5 h-5 mt-1"></i>
                                        <div>
                                            <p className="text-blue-800 font-medium">
                                                Nouveaux jeux bientôt disponibles !
                                            </p>
                                            <p className="text-blue-600 text-sm mt-1">
                                                Cliquez sur un jeu pour voir les détails et commencer à jouer.
                                            </p>
                                        </div>
                                    </div>
                                </div>

                                {/* Grille de jeux */}
                                <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {filteredGames.map((game) => (
                                        <div
                                            key={game.id}
                                            onClick={() => {
                                                setSelectedGame(game);
                                                tracking.logEvent('GAME_SELECTED', { game: game.title });
                                            }}
                                            data-track={`game_card_${game.id}`}
                                            className="bg-white rounded-2xl shadow-md hover:shadow-xl transition-all cursor-pointer game-card-hover overflow-hidden border-2 border-transparent hover:border-orange-200"
                                        >
                                            {/* Header coloré */}
                                            <div className={`bg-gradient-to-r from-${game.color}-400 to-${game.color}-500 p-6 text-white relative`}>
                                                {game.status === 'soon' && (
                                                    <div className="absolute top-2 right-2 bg-white bg-opacity-90 text-orange-600 px-3 py-1 rounded-full text-xs font-bold">
                                                        Bientôt
                                                    </div>
                                                )}
                                                <div className="bg-white bg-opacity-20 w-16 h-16 rounded-xl flex items-center justify-center mb-4">
                                                    <i data-lucide={game.icon} className="w-8 h-8"></i>
                                                </div>
                                                <h3 className="text-2xl font-bold">{game.title}</h3>
                                            </div>

                                            {/* Contenu */}
                                            <div className="p-6">
                                                <p className="text-gray-600 mb-4 min-h-[48px]">{game.description}</p>
                                                
                                                <div className="space-y-2 mb-4">
                                                    <div className="flex items-center gap-2 text-sm">
                                                        <i data-lucide="signal" className={`w-4 h-4 text-${game.color}-500`}></i>
                                                        <span className="font-semibold text-gray-700">Difficulté:</span>
                                                        <span className={`px-2 py-0.5 rounded-full text-xs font-bold ${
                                                            game.difficulty === 'Facile' ? 'bg-green-100 text-green-700' :
                                                            game.difficulty === 'Moyen' ? 'bg-yellow-100 text-yellow-700' :
                                                            'bg-red-100 text-red-700'
                                                        }`}>
                                                            {game.difficulty}
                                                        </span>
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-2 text-sm text-gray-600">
                                                        <i data-lucide="users" className="w-4 h-4"></i>
                                                        <span>{game.players}</span>
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-2 text-sm text-gray-600">
                                                        <i data-lucide="clock" className="w-4 h-4"></i>
                                                        <span>{game.duration}</span>
                                                    </div>
                                                </div>

                                                <button className={`w-full bg-gradient-to-r from-${game.color}-500 to-${game.color}-600 text-white font-bold py-3 px-4 rounded-xl hover:shadow-lg transition-all flex items-center justify-center gap-2`}>
                                                    {game.status === 'available' ? (
                                                        <>
                                                            <i data-lucide="play"></i>
                                                            Jouer
                                                        </>
                                                    ) : (
                                                        <>
                                                            <i data-lucide="info"></i>
                                                            En savoir plus
                                                        </>
                                                    )}
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Message si aucun jeu */}
                                {filteredGames.length === 0 && (
                                    <div className="bg-white p-12 rounded-2xl shadow text-center">
                                        <i data-lucide="frown" className="w-16 h-16 mx-auto text-gray-300 mb-4"></i>
                                        <p className="text-gray-400 text-lg">
                                            Aucun jeu ne correspond à ce filtre.
                                        </p>
                                        <button
                                            onClick={() => {
                                                setFilterDifficulty('all');
                                                tracking.logEvent('GAME_FILTER_RESET');
                                            }}
                                            data-track="filter_reset"
                                            className="mt-4 text-orange-500 font-semibold hover:text-orange-600"
                                        >
                                            Voir tous les jeux
                                        </button>
                                    </div>
                                )}
                            </div>
                        </div>
                    );
                };

                // Modal de détails du jeu
                const GameDetailModal = ({ game }) => {
                    if (!game) return null;

                    return (
                        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                            <div className="bg-white rounded-3xl shadow-2xl max-w-2xl w-full max-h-[90vh] overflow-hidden">
                                {/* Header */}
                                <div className={`bg-gradient-to-r from-${game.color}-500 to-${game.color}-600 p-8 text-white`}>
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-center gap-4">
                                            <div className="bg-white bg-opacity-20 p-4 rounded-xl">
                                                <i data-lucide={game.icon} className="w-10 h-10"></i>
                                            </div>
                                            <div>
                                                <h2 className="text-3xl font-bold">{game.title}</h2>
                                                <p className="text-white text-opacity-90 mt-1">{game.description}</p>
                                            </div>
                                        </div>
                                        <button
                                            onClick={() => {
                                                setSelectedGame(null);
                                                tracking.logEvent('GAME_DETAIL_CLOSED', { game: game.title });
                                            }}
                                            data-track="game_detail_close"
                                            className="text-white hover:bg-white hover:bg-opacity-20 p-2 rounded-lg transition-colors"
                                        >
                                            <i data-lucide="x" className="w-6 h-6"></i>
                                        </button>
                                    </div>
                                </div>

                                {/* Contenu */}
                                <div className="p-8">
                                    {/* Informations */}
                                    <div className="grid grid-cols-3 gap-4 mb-8">
                                        <div className="bg-gray-50 p-4 rounded-xl text-center">
                                            <i data-lucide="signal" className={`w-6 h-6 mx-auto mb-2 text-${game.color}-500`}></i>
                                            <p className="text-sm text-gray-600 mb-1">Difficulté</p>
                                            <p className="font-bold text-gray-800">{game.difficulty}</p>
                                        </div>
                                        <div className="bg-gray-50 p-4 rounded-xl text-center">
                                            <i data-lucide="users" className={`w-6 h-6 mx-auto mb-2 text-${game.color}-500`}></i>
                                            <p className="text-sm text-gray-600 mb-1">Joueurs</p>
                                            <p className="font-bold text-gray-800">{game.players}</p>
                                        </div>
                                        <div className="bg-gray-50 p-4 rounded-xl text-center">
                                            <i data-lucide="clock" className={`w-6 h-6 mx-auto mb-2 text-${game.color}-500`}></i>
                                            <p className="text-sm text-gray-600 mb-1">Durée</p>
                                            <p className="font-bold text-gray-800">{game.duration}</p>
                                        </div>
                                    </div>

                                    

                                    {/* Status badge */}
                                    {game.status === 'soon' && (
                                        <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4 rounded-xl mb-6">
                                            <div className="flex items-start gap-3">
                                                <i data-lucide="construction" className="text-yellow-500 w-5 h-5 mt-1"></i>
                                                <div>
                                                    <p className="text-yellow-800 font-medium">
                                                        Ce jeu arrive bientôt !
                                                    </p>
                                                    <p className="text-yellow-600 text-sm mt-1">
                                                        Nous travaillons activement pour vous l'apporter. Revenez vite !
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

                                    {/* Actions */}
                                    <div className="flex gap-3">
                                        {game.status === 'available' ? (
    <button
        onClick={() => {
            tracking.logEvent('GAME_STARTED', { game: game.title });
            if (game.id === 'morpion') {
                setActiveGame('morpion');
                setSelectedGame(null);
            } else if (game.id === 'memory') {
                setActiveGame('memory');
                setSelectedGame(null);
            } else if (game.id === 'puissance4') {
                setActiveGame('puissance4');
                setSelectedGame(null);
            } else if (game.id === 'baccalaureat') {
                setActiveGame('baccalaureat');
                setSelectedGame(null);
            } else if (game.id === 'pictionary') {
                setActiveGame('pictionary');
                setSelectedGame(null);
            } else if (game.id === 'pendu') {
                setActiveGame('pendu');
                setSelectedGame(null);
            }
        }}
                                                data-track={`game_start_${game.id}`}
                                                className={`flex-1 bg-gradient-to-r from-${game.color}-500 to-${game.color}-600 hover:from-${game.color}-600 hover:to-${game.color}-700 text-white font-bold py-4 px-6 rounded-xl transition-all flex items-center justify-center gap-2 shadow-lg`}
                                            >
                                                <i data-lucide="play"></i>
                                                Commencer à jouer
                                            </button>
                                        ) : (
                                            <button
                                                disabled
                                                className="flex-1 bg-gray-300 text-gray-500 font-bold py-4 px-6 rounded-xl cursor-not-allowed flex items-center justify-center gap-2"
                                            >
                                                <i data-lucide="lock"></i>
                                                Pas encore disponible
                                            </button>
                                        )}
                                        
                                    </div>
                                </div>
                            </div>
                        </div>
                    );
                };

                return (
    <>
        {selectedGame && <GameDetailModal game={selectedGame} />}
        {activeGame === 'morpion' ? (
            <MorpionGame onBack={() => setActiveGame(null)} />
        ) : activeGame === 'memory' ? (
            <MemoryGame onBack={() => setActiveGame(null)} />
        ) : activeGame === 'puissance4' ? (
            <Puissance4Game onBack={() => setActiveGame(null)} />
        ) : activeGame === 'baccalaureat' ? (
            <BaccalaureatGame onBack={() => setActiveGame(null)} />
        ) : activeGame === 'pictionary' ? (
            <PictionaryGame onBack={() => setActiveGame(null)} />
        ) : activeGame === 'pendu' ? (
            <PenduGame onBack={() => setActiveGame(null)} />
        ) : (
            <>
                {(() => {
                    switch (screen) {
                        case 'welcome': return <WelcomeScreen />;
                        case 'games': return <GamesScreen />;
                        default: return <GamesScreen />;
                    }
                })()}
            </>
        )}
    </>
);
            };
            
            // ========== JEU PENDU MULTIJOUEUR ==========
// ========== JEU PENDU MULTIJOUEUR ==========
const PenduGame = ({ onBack }) => {
    const CATEGORIES = ['Animaux','Pays','Films','Sports','Nourriture','Objets'];

    // ---- ÉTATS ----
    const [screen, setScreen] = useState('menu'); // menu | setup | waiting | playing | finished
    const [gameId, setGameId] = useState(null);
    const [gameCode, setGameCode] = useState('');
    const [joinCode, setJoinCode] = useState('');
    const [error, setError] = useState('');
    const [loading, setLoading] = useState(false);
    const [selectedCategory, setSelectedCategory] = useState('Animaux');

    // État du jeu (polling)
    const [gameState, setGameState] = useState(null);

    // Saisie lettre
    const [letterInput, setLetterInput] = useState('');
    const [wordInput, setWordInput] = useState('');
    const [lastResult, setLastResult] = useState(null); // null | 'correct' | 'wrong'
    const [showWordInput, setShowWordInput] = useState(false);

    // Rematch
    const [rematchCategory, setRematchCategory] = useState('Animaux');
    const [showRematch, setShowRematch] = useState(false);

    useEffect(() => { lucide.createIcons(); }, [screen, gameState, showWordInput, showRematch]);

    // ---- POLLING ----
    useEffect(() => {
        if(!gameId) return;
        const interval = setInterval(pollGame, 1000);
        return () => clearInterval(interval);
    }, [gameId]);

    const pollGame = async () => {
        if(!gameId) return;
        try {
            const res = await fetch(`api/pendu.php?action=poll&game_id=${gameId}`);
            const data = await res.json();
            if(!data.success) return;
            setGameState(data);
            if(data.status === 'waiting') setScreen('waiting');
            else if(data.status === 'playing') setScreen('playing');
            else if(data.status === 'won' || data.status === 'lost') setScreen('finished');
        } catch(e){ console.error(e); }
    };

    // ---- ACTIONS API ----
    const createGame = async () => {
        setLoading(true); setError('');
        try {
            const res = await fetch(`api/pendu.php?action=create_game&category=${encodeURIComponent(selectedCategory)}`);
            const data = await res.json();
            if(data.success){
                setGameId(data.game_id);
                setGameCode(data.game_code);
                setScreen('waiting');
            } else setError(data.error || 'Erreur');
        } catch(e){ setError('Erreur réseau'); }
        setLoading(false);
    };

    const joinGame = async () => {
        if(!joinCode.trim()){ setError('Entre un code'); return; }
        setLoading(true); setError('');
        try {
            const res = await fetch(`api/pendu.php?action=join_game&game_code=${joinCode.trim().toUpperCase()}`);
            const data = await res.json();
            if(data.success){
                setGameId(data.game_id);
                setScreen('playing');
            } else setError(data.error || 'Code invalide');
        } catch(e){ setError('Erreur réseau'); }
        setLoading(false);
    };

    const guessLetter = async (letter) => {
        const l = letter.toUpperCase();
        if(!l || !/^[A-Z]$/.test(l)) return;
        if(gameState?.letters?.includes(l)) return;
        try {
            const res = await fetch(`api/pendu.php?action=guess_letter&game_id=${gameId}&letter=${l}`);
            const data = await res.json();
            if(data.success){
                setLastResult(data.correct ? 'correct' : 'wrong');
                setTimeout(() => setLastResult(null), 700);
            }
        } catch(e){}
        setLetterInput('');
    };

    const guessWord = async () => {
        if(!wordInput.trim()) return;
        try {
            const res = await fetch(`api/pendu.php?action=guess_word&game_id=${gameId}`, {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ word: wordInput.trim() })
            });
            const data = await res.json();
            if(data.success && !data.correct){
                setLastResult('wrong');
                setTimeout(() => setLastResult(null), 700);
            }
        } catch(e){}
        setWordInput('');
        setShowWordInput(false);
    };

    const doRematch = async () => {
        setLoading(true); setError('');
        try {
            const res = await fetch(`api/pendu.php?action=rematch&game_id=${gameId}&category=${encodeURIComponent(rematchCategory)}`);
            const data = await res.json();
            if(data.success){
                setGameId(data.game_id);
                setGameCode(data.game_code);
                setShowRematch(false);
                setLastResult(null);
                setScreen('playing');
            } else setError(data.error || 'Erreur');
        } catch(e){ setError('Erreur réseau'); }
        setLoading(false);
    };

    // ---- DESSIN DU PENDU SVG ----
    const HangmanSVG = ({ errors }) => {
        const parts = [
            // 0: potence base
            <line key="base" x1="10" y1="195" x2="110" y2="195" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>,
            // 1: potence verticale
            <line key="vertical" x1="40" y1="10" x2="40" y2="195" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>,
            // 2: potence horizontale
            <line key="horizontal" x1="40" y1="10" x2="130" y2="10" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>,
            // 3: corde
            <line key="rope" x1="130" y1="10" x2="130" y2="35" stroke="#374151" strokeWidth="3" strokeLinecap="round"/>,
            // 4: tête
            <circle key="head" cx="130" cy="50" r="15" stroke="#ef4444" strokeWidth="3" fill="none"/>,
            // 5: corps
            <line key="body" x1="130" y1="65" x2="130" y2="130" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // 6: bras gauche
            <line key="arm-left" x1="130" y1="80" x2="105" y2="110" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // 7 (erreur 7 = jambe droite, mais on en a 7 donc les deux bras et deux jambes)
        ];
        // On montre les parties selon le nombre d'erreurs
        // erreurs 0: potence complète (3 parties fixes)
        // puis tête, corps, bras g, bras d, jambe g, jambe d à chaque erreur
        const bodyParts = [
            // tête
            <circle key="head" cx="130" cy="50" r="15" stroke="#ef4444" strokeWidth="3" fill="#fef2f2"/>,
            // corps
            <line key="body" x1="130" y1="65" x2="130" y2="130" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // bras gauche
            <line key="arm-l" x1="130" y1="80" x2="105" y2="110" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // bras droit
            <line key="arm-r" x1="130" y1="80" x2="155" y2="110" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // jambe gauche
            <line key="leg-l" x1="130" y1="130" x2="105" y2="165" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // jambe droite
            <line key="leg-r" x1="130" y1="130" x2="155" y2="165" stroke="#ef4444" strokeWidth="3" strokeLinecap="round"/>,
            // visage triste (yeux et bouche)
            <g key="face">
                <circle cx="124" cy="46" r="2" fill="#ef4444"/>
                <circle cx="136" cy="46" r="2" fill="#ef4444"/>
                <path d="M 122 57 Q 130 52 138 57" stroke="#ef4444" strokeWidth="2" fill="none" strokeLinecap="round"/>
            </g>
        ];

        return (
            <svg viewBox="0 0 200 200" className="w-full max-w-[200px] mx-auto">
                {/* Potence (toujours visible) */}
                <line x1="10" y1="195" x2="110" y2="195" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>
                <line x1="40" y1="10" x2="40" y2="195" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>
                <line x1="40" y1="10" x2="130" y2="10" stroke="#374151" strokeWidth="4" strokeLinecap="round"/>
                <line x1="130" y1="10" x2="130" y2="35" stroke="#6b7280" strokeWidth="3" strokeLinecap="round"/>
                {/* Corps du pendu selon erreurs */}
                {bodyParts.slice(0, errors)}
            </svg>
        );
    };

    // ---- CLAVIER VISUEL ----
    const Keyboard = ({ letters, onGuess, disabled }) => {
        const rows = [
            ['A','Z','E','R','T','Y','U','I','O','P'],
            ['Q','S','D','F','G','H','J','K','L','M'],
            ['W','X','C','V','B','N']
        ];
        return (
            <div className="space-y-2">
                {rows.map((row, ri) => (
                    <div key={ri} className="flex justify-center gap-1 flex-wrap">
                        {row.map(l => {
                            const used = letters?.includes(l);
                            const inWord = gameState?.masked_word?.includes(l);
                            return (
                                <button
                                    key={l}
                                    onClick={() => !used && !disabled && onGuess(l)}
                                    disabled={used || disabled}
                                    className={`w-9 h-10 rounded-lg font-bold text-sm transition-all ${
                                        used
                                            ? inWord
                                                ? 'bg-green-100 text-green-600 border-2 border-green-300'
                                                : 'bg-red-100 text-red-400 border-2 border-red-200'
                                            : 'bg-purple-100 text-purple-700 border-2 border-purple-200 hover:bg-purple-500 hover:text-white hover:scale-110'
                                    }`}
                                >
                                    {l}
                                </button>
                            );
                        })}
                    </div>
                ))}
            </div>
        );
    };

    // ========== ÉCRANS ==========

    // --- MENU ---
    if(screen === 'menu'){
        return (
            <div className="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
                    <button onClick={onBack} className="flex items-center gap-2 text-gray-600 font-bold hover:text-purple-600 mb-6 transition-colors">
                        <i data-lucide="arrow-left"></i> Retour
                    </button>
                    <div className="text-center mb-8">
                        <div className="text-6xl mb-3">🪢</div>
                        <h1 className="text-3xl font-extrabold text-gray-800 mb-2">Le Pendu</h1>
                        <p className="text-gray-500">Multijoueur en ligne</p>
                    </div>

                    {error && <div className="bg-red-50 text-red-600 p-3 rounded-xl mb-4 text-sm font-semibold text-center">{error}</div>}

                    {/* Créer une partie */}
                    <div className="bg-purple-50 rounded-2xl p-5 mb-4">
                        <p className="font-bold text-purple-800 mb-3 flex items-center gap-2">
                            <i data-lucide="plus-circle" className="w-4 h-4"></i>
                            Créer une partie
                        </p>
                        <p className="text-sm text-gray-500 mb-3">Choisissez la catégorie du mot :</p>
                        <div className="grid grid-cols-2 gap-2 mb-4">
                            {CATEGORIES.map(cat => (
                                <button
                                    key={cat}
                                    onClick={() => setSelectedCategory(cat)}
                                    className={`py-2 px-3 rounded-xl font-semibold text-sm transition-all ${selectedCategory === cat ? 'bg-purple-500 text-white shadow-md scale-105' : 'bg-white text-gray-600 border-2 border-purple-100 hover:border-purple-300'}`}
                                >
                                    {cat}
                                </button>
                            ))}
                        </div>
                        <button
                            onClick={createGame}
                            disabled={loading}
                            className="w-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white font-bold py-3 rounded-xl shadow-lg hover:scale-[1.02] transition flex items-center justify-center gap-2"
                        >
                            <i data-lucide="play"></i> Créer et partager le code
                        </button>
                    </div>

                    {/* Rejoindre */}
                    <div className="bg-gray-50 rounded-2xl p-5">
                        <p className="font-bold text-gray-700 mb-3 flex items-center gap-2">
                            <i data-lucide="log-in" className="w-4 h-4"></i>
                            Rejoindre une partie
                        </p>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={joinCode}
                                onChange={e => setJoinCode(e.target.value.toUpperCase())}
                                placeholder="CODE"
                                maxLength={6}
                                className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl text-center font-black text-2xl uppercase focus:border-purple-400 focus:outline-none tracking-widest"
                            />
                            <button
                                onClick={joinGame}
                                disabled={loading}
                                className="bg-purple-500 text-white font-bold px-5 rounded-xl hover:bg-purple-600 transition"
                            >
                                OK
                            </button>
                        </div>
                        <p className="text-xs text-gray-400 mt-2 text-center">Ton ami te partage un code à 6 lettres</p>
                    </div>
                </div>
            </div>
        );
    }

    // --- ATTENTE (créateur attend que l'autre rejoigne) ---
    if(screen === 'waiting'){
        return (
            <div className="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">
                    <div className="text-5xl mb-4 animate-bounce">⏳</div>
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Partie créée !</h2>
                    <p className="text-gray-500 mb-6">Partagez ce code à votre adversaire :</p>

                    <div className="bg-purple-50 rounded-2xl p-6 mb-6">
                        <p className="text-5xl font-black text-purple-600 tracking-widest mb-2">{gameCode}</p>
                        <p className="text-sm text-gray-500">Catégorie : <strong>{selectedCategory}</strong></p>
                        {gameState?.word && (
                            <p className="text-sm text-purple-700 font-bold mt-2">
                                Mot à faire deviner : <span className="text-purple-900">{gameState.word}</span>
                            </p>
                        )}
                    </div>

                    <div className="flex items-center gap-3 bg-gray-50 rounded-xl p-4 mb-6">
                        <div className="animate-spin w-5 h-5 border-2 border-purple-500 border-t-transparent rounded-full"></div>
                        <p className="text-gray-600 font-semibold">En attente que l'adversaire rejoigne...</p>
                    </div>

                    <button onClick={() => { setScreen('menu'); setGameId(null); setGameCode(''); }} className="text-gray-400 hover:text-gray-600 font-medium text-sm">
                        Annuler
                    </button>
                </div>
            </div>
        );
    }

    // --- JEU EN COURS ---
    if(screen === 'playing' && gameState){
        const amGuesser = gameState.am_guesser;
        const amCreator = gameState.am_creator;
        const errors    = gameState.errors;
        const maxErrors = gameState.max_errors;
        const letters   = gameState.letters || [];
        const masked    = gameState.masked_word || [];
        const isGameOver = gameState.status !== 'playing';

        return (
            <div className="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 p-4">
                <div className="max-w-lg mx-auto">

                    {/* Header */}
                    <div className="flex items-center justify-between mb-4">
                        <button onClick={() => { setScreen('menu'); setGameId(null); }} className="flex items-center gap-2 text-gray-500 hover:text-purple-600 font-bold transition-colors text-sm">
                            <i data-lucide="arrow-left"></i> Menu
                        </button>
                        <div className="text-center">
                            <span className="bg-purple-100 text-purple-700 px-3 py-1 rounded-full text-sm font-bold">
                                {gameState.category}
                            </span>
                        </div>
                        <div className="text-right">
                            <p className="text-xs text-gray-400">Erreurs</p>
                            <p className={`font-black text-lg ${errors >= 5 ? 'text-red-600' : 'text-gray-700'}`}>{errors}/{maxErrors}</p>
                        </div>
                    </div>

                    {/* Rôles */}
                    <div className="grid grid-cols-2 gap-3 mb-4">
                        <div className={`rounded-xl p-3 text-center ${amCreator ? 'bg-indigo-100 ring-2 ring-indigo-400' : 'bg-white shadow'}`}>
                            <p className="text-xs text-gray-400 mb-0.5">✏️ Fait deviner</p>
                            <p className="font-bold text-gray-800 text-sm truncate">{gameState.creator_name}</p>
                            {amCreator && <span className="text-xs text-indigo-600 font-bold">C'est toi</span>}
                        </div>
                        <div className={`rounded-xl p-3 text-center ${amGuesser ? 'bg-purple-100 ring-2 ring-purple-400' : 'bg-white shadow'}`}>
                            <p className="text-xs text-gray-400 mb-0.5">🔍 Devine</p>
                            <p className="font-bold text-gray-800 text-sm truncate">{gameState.guesser_name || '...'}</p>
                            {amGuesser && <span className="text-xs text-purple-600 font-bold">C'est toi</span>}
                        </div>
                    </div>

                    {/* Pendu SVG */}
                    <div className="bg-white rounded-2xl shadow-lg p-4 mb-4 flex justify-center">
                        <HangmanSVG errors={errors} />
                    </div>

                    {/* Mot masqué */}
                    <div className="bg-white rounded-2xl shadow-lg p-4 mb-4">
                        {amCreator && (
                            <p className="text-xs text-gray-400 text-center mb-2">Mot : <strong className="text-indigo-600">{gameState.word}</strong></p>
                        )}
                        <div className="flex justify-center gap-2 flex-wrap">
                            {masked.map((char, i) => (
                                <div key={i} className="flex flex-col items-center">
                                    <span className={`text-2xl font-black min-w-[28px] text-center ${char !== '_' ? 'text-purple-600' : 'text-gray-300'}`}>
                                        {char === '_' ? ' ' : char}
                                    </span>
                                    <div className="w-7 h-0.5 bg-gray-400 mt-1 rounded"></div>
                                </div>
                            ))}
                        </div>
                        <p className="text-center text-xs text-gray-400 mt-2">{masked.length} lettres</p>
                    </div>

                    {/* Feedback dernière lettre */}
                    {lastResult && (
                        <div className={`text-center py-2 rounded-xl mb-3 font-bold text-sm ${lastResult === 'correct' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                            {lastResult === 'correct' ? '✅ Lettre dans le mot !' : '❌ Lettre absente !'}
                        </div>
                    )}

                    {/* Zone de jeu (devineur seulement) */}
                    {amGuesser && !isGameOver && (
                        <div className="bg-white rounded-2xl shadow-lg p-4 mb-4">
                            <Keyboard
                                letters={letters}
                                onGuess={guessLetter}
                                disabled={isGameOver}
                            />

                            {/* Deviner le mot entier */}
                            <div className="mt-4 pt-3 border-t border-gray-100">
                                {!showWordInput ? (
                                    <button
                                        onClick={() => setShowWordInput(true)}
                                        className="w-full text-purple-500 text-sm font-semibold hover:text-purple-700 py-2"
                                    >
                                        Je connais le mot entier →
                                    </button>
                                ) : (
                                    <div className="flex gap-2">
                                        <input
                                            type="text"
                                            value={wordInput}
                                            onChange={e => setWordInput(e.target.value.toUpperCase())}
                                            onKeyDown={e => e.key === 'Enter' && guessWord()}
                                            placeholder="Entrez le mot..."
                                            className="flex-1 px-3 py-2 border-2 border-purple-200 rounded-xl focus:border-purple-400 focus:outline-none text-sm font-bold uppercase"
                                            autoFocus
                                        />
                                        <button onClick={guessWord} className="bg-purple-500 text-white font-bold px-4 rounded-xl hover:bg-purple-600 transition">✓</button>
                                        <button onClick={() => setShowWordInput(false)} className="text-gray-400 hover:text-gray-600 px-2">✕</button>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* Vue créateur pendant le jeu */}
                    {amCreator && !isGameOver && (
                        <div className="bg-indigo-50 rounded-2xl p-4 mb-4 text-center">
                            <p className="text-indigo-700 font-semibold text-sm">
                                👀 Tu regardes {gameState.guesser_name || 'l\'adversaire'} deviner...
                            </p>
                            <div className="flex flex-wrap gap-1 justify-center mt-3">
                                {letters.map(l => (
                                    <span key={l} className={`px-2 py-1 rounded-lg text-xs font-bold ${gameState.word?.includes(l) ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-500'}`}>{l}</span>
                                ))}
                            </div>
                        </div>
                    )}
                </div>
            </div>
        );
    }

    // --- FIN DE PARTIE ---
    if(screen === 'finished' && gameState){
        const won    = gameState.status === 'won';
        const amCreator = gameState.am_creator;
        const amGuesser = gameState.am_guesser;

        return (
            <div className="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">
                    <div className="text-7xl mb-4">{won ? '🎉' : '💀'}</div>
                    <h1 className="text-3xl font-extrabold text-gray-800 mb-2">
                        {won ? 'Bravo !' : 'Perdu !'}
                    </h1>
                    <p className="text-gray-500 mb-4">
                        {won
                            ? `${gameState.guesser_name} a trouvé le mot !`
                            : `${gameState.guesser_name} n'a pas trouvé...`
                        }
                    </p>

                    <div className={`text-3xl font-black px-6 py-4 rounded-2xl mb-6 ${won ? 'bg-gradient-to-r from-purple-500 to-indigo-500 text-white' : 'bg-red-100 text-red-700'}`}>
                        {gameState.word}
                    </div>

                    <div className="flex justify-center gap-2 flex-wrap mb-6">
                        {(gameState.masked_word || []).map((char, i) => (
                            <div key={i} className="flex flex-col items-center">
                                <span className="text-xl font-black min-w-[24px] text-center text-purple-600">{char}</span>
                                <div className="w-6 h-0.5 bg-gray-400 mt-1 rounded"></div>
                            </div>
                        ))}
                    </div>

                    <div className="bg-gray-50 rounded-xl p-3 mb-6 text-sm text-gray-600">
                        {gameState.errors} erreur{gameState.errors > 1 ? 's' : ''} sur {gameState.max_errors} autorisées
                    </div>

                    {/* Rejouer — seulement le créateur peut lancer un nouveau mot */}
                    {amCreator ? (
                        !showRematch ? (
                            <button
                                onClick={() => setShowRematch(true)}
                                className="w-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition mb-3"
                            >
                                🔄 Choisir un nouveau mot
                            </button>
                        ) : (
                            <div className="bg-purple-50 rounded-2xl p-4 mb-3">
                                <p className="text-sm font-bold text-purple-700 mb-3">Nouvelle catégorie :</p>
                                <div className="grid grid-cols-2 gap-2 mb-3">
                                    {CATEGORIES.map(cat => (
                                        <button
                                            key={cat}
                                            onClick={() => setRematchCategory(cat)}
                                            className={`py-2 px-3 rounded-xl font-semibold text-sm transition-all ${rematchCategory === cat ? 'bg-purple-500 text-white' : 'bg-white text-gray-600 border border-purple-200 hover:border-purple-400'}`}
                                        >
                                            {cat}
                                        </button>
                                    ))}
                                </div>
                                <button
                                    onClick={doRematch}
                                    disabled={loading}
                                    className="w-full bg-gradient-to-r from-purple-500 to-indigo-500 text-white font-bold py-3 rounded-xl shadow hover:scale-[1.02] transition"
                                >
                                    Lancer la revanche !
                                </button>
                            </div>
                        )
                    ) : (
                        <div className="bg-gray-50 rounded-xl p-4 mb-3 text-gray-500 text-sm font-semibold">
                            En attente que {gameState.creator_name} propose un nouveau mot...
                        </div>
                    )}

                    <button onClick={() => { setScreen('menu'); setGameId(null); setGameCode(''); setGameState(null); setShowRematch(false); }}
                        className="w-full bg-gray-100 text-gray-600 font-bold py-3 rounded-xl hover:bg-gray-200 transition">
                        Retour au menu
                    </button>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gradient-to-br from-purple-50 to-indigo-50 flex items-center justify-center">
            <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-600"></div>
        </div>
    );
};


            // ========== JEU BACCALAURÉAT (LOCAL) ==========
const BaccalaureatGame = ({ onBack }) => {
    const CATEGORIES = ['Prénom', 'Animal', 'Pays', 'Fruit/Légume', 'Métier', 'Film/Série'];
    const LETTERS = 'ABCDEFGHIJLMNOPRSTV'.split('');

    const [screen, setScreen] = useState('menu'); // menu, playing, reveal
    const [letter, setLetter] = useState('');
    const [answers, setAnswers] = useState({});
    const [timeLeft, setTimeLeft] = useState(90);
    const [timerActive, setTimerActive] = useState(false);
    const [players, setPlayers] = useState([{ name: '' }, { name: '' }]);
    const [setupDone, setSetupDone] = useState(false);
    const [allAnswers, setAllAnswers] = useState([]); // [{playerName, answers}]
    const [currentPlayerIdx, setCurrentPlayerIdx] = useState(0);
    const [scores, setScores] = useState({});

    useEffect(() => { lucide.createIcons(); }, [screen, setupDone]);

    useEffect(() => {
        if (!timerActive) return;
        if (timeLeft <= 0) { setTimerActive(false); nextPlayer(); return; }
        const t = setTimeout(() => setTimeLeft(t => t - 1), 1000);
        return () => clearTimeout(t);
    }, [timerActive, timeLeft]);

    const startGame = () => {
        const l = LETTERS[Math.floor(Math.random() * LETTERS.length)];
        setLetter(l);
        setAllAnswers([]);
        setCurrentPlayerIdx(0);
        setScores({});
        setScreen('playing');
        resetRound();
    };

    const resetRound = () => {
        setAnswers({});
        setTimeLeft(90);
        setTimerActive(true);
    };

    const nextPlayer = () => {
        // Save current player answers
        const playerName = players[currentPlayerIdx]?.name || `Joueur ${currentPlayerIdx + 1}`;
        setAllAnswers(prev => [...prev, { playerName, answers }]);
        
        if (currentPlayerIdx + 1 < players.length) {
            setCurrentPlayerIdx(currentPlayerIdx + 1);
            resetRound();
        } else {
            // All players done → reveal
            setTimerActive(false);
            setScreen('reveal');
        }
    };

    const finishEarly = () => {
        setTimerActive(false);
        nextPlayer();
    };

    const calcScores = (finalAnswers) => {
        const sc = {};
        players.forEach((p, i) => { sc[p.name || `Joueur ${i+1}`] = 0; });
        
        CATEGORIES.forEach((_, catIdx) => {
            const catAnswers = finalAnswers.map(fa => (fa.answers[catIdx] || '').trim().toLowerCase());
            finalAnswers.forEach((fa, pIdx) => {
                const ans = catAnswers[pIdx];
                if (!ans) return;
                const firstLetter = ans[0]?.toUpperCase();
                if (firstLetter !== letter) return; // doesn't start with letter
                const isDuplicate = catAnswers.filter(a => a === ans).length > 1;
                const playerName = fa.playerName;
                sc[playerName] = (sc[playerName] || 0) + (isDuplicate ? 5 : 10);
            });
        });
        return sc;
    };

    const timerColor = timeLeft > 30 ? 'text-green-600' : timeLeft > 10 ? 'text-orange-500' : 'text-red-600';
    const timerBg = timeLeft > 30 ? 'bg-green-50' : timeLeft > 10 ? 'bg-orange-50' : 'bg-red-50';

    if (!setupDone) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
                    <button onClick={onBack} className="flex items-center gap-2 text-gray-600 font-bold hover:text-green-600 mb-6">
                        <i data-lucide="arrow-left"></i> Retour
                    </button>
                    <div className="text-center mb-8">
                        <div className="text-6xl mb-3">📝</div>
                        <h1 className="text-3xl font-extrabold text-gray-800 mb-2">Baccalauréat</h1>
                        <p className="text-gray-500">Combien de joueurs ?</p>
                    </div>
                    <div className="space-y-3 mb-6">
                        {players.map((p, i) => (
                            <div key={i} className="flex gap-3 items-center">
                                <div className="bg-green-100 text-green-700 font-bold w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0">{i+1}</div>
                                <input
                                    type="text"
                                    value={p.name}
                                    onChange={e => { const np = [...players]; np[i].name = e.target.value; setPlayers(np); }}
                                    placeholder={`Prénom joueur ${i+1}`}
                                    className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-400 focus:outline-none"
                                    maxLength={20}
                                />
                                {players.length > 2 && (
                                    <button onClick={() => setPlayers(players.filter((_, idx) => idx !== i))} className="text-red-400 hover:text-red-600 font-bold text-xl">×</button>
                                )}
                            </div>
                        ))}
                    </div>
                    {players.length < 6 && (
                        <button onClick={() => setPlayers([...players, { name: '' }])} className="w-full border-2 border-dashed border-green-300 text-green-600 font-semibold py-3 rounded-xl hover:bg-green-50 transition mb-4">
                            + Ajouter un joueur
                        </button>
                    )}
                    <button
                        onClick={() => setSetupDone(true)}
                        className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition flex items-center justify-center gap-2"
                    >
                        <i data-lucide="play"></i> C'est parti !
                    </button>
                </div>
            </div>
        );
    }

    if (screen === 'menu') {
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">
                    <div className="text-6xl mb-4">📝</div>
                    <h1 className="text-3xl font-bold text-gray-800 mb-2">Baccalauréat</h1>
                    <p className="text-gray-500 mb-6">Une lettre aléatoire, 6 catégories, 90 secondes !</p>
                    <div className="bg-green-50 p-4 rounded-2xl mb-6 text-sm text-green-800">
                        <strong>Règles :</strong> Trouvez un mot commençant par la lettre imposée pour chaque catégorie. 10 pts si unique, 5 pts si doublon !
                    </div>
                    <div className="flex flex-wrap gap-2 justify-center mb-6">
                        {players.map((p, i) => (
                            <span key={i} className="bg-green-100 text-green-700 px-3 py-1 rounded-full font-semibold text-sm">{p.name || `Joueur ${i+1}`}</span>
                        ))}
                    </div>
                    <button onClick={startGame} className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition flex items-center justify-center gap-2 text-lg">
                        <i data-lucide="play"></i> Lancer une partie
                    </button>
                    <button onClick={onBack} className="w-full mt-3 bg-gray-100 text-gray-600 font-medium py-3 rounded-xl hover:bg-gray-200 transition">Retour aux jeux</button>
                </div>
            </div>
        );
    }

    if (screen === 'playing') {
        const currentName = players[currentPlayerIdx]?.name || `Joueur ${currentPlayerIdx + 1}`;
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 p-4">
                <div className="max-w-2xl mx-auto">
                    {/* Header */}
                    <div className="bg-white rounded-2xl shadow-lg p-4 mb-4 flex items-center justify-between">
                        <div className="bg-gradient-to-r from-green-500 to-teal-500 text-white px-6 py-3 rounded-xl text-center">
                            <p className="text-xs opacity-80 mb-0.5">Lettre</p>
                            <p className="text-5xl font-black leading-none">{letter}</p>
                        </div>
                        <div className="text-center">
                            <p className="text-sm text-gray-500 mb-1">Tour de</p>
                            <p className="text-xl font-bold text-gray-800">{currentName}</p>
                            <p className="text-sm text-gray-400">{currentPlayerIdx + 1} / {players.length}</p>
                        </div>
                        <div className={`${timerBg} rounded-xl px-4 py-3 text-center`}>
                            <p className="text-xs text-gray-500 mb-0.5">Temps</p>
                            <p className={`text-3xl font-black ${timerColor}`}>{timeLeft}s</p>
                        </div>
                    </div>

                    {/* Champs réponses */}
                    <div className="bg-white rounded-2xl shadow-lg p-6 mb-4">
                        <div className="space-y-3">
                            {CATEGORIES.map((cat, i) => (
                                <div key={i} className="flex items-center gap-3">
                                    <div className="bg-green-500 text-white w-8 h-8 rounded-full flex items-center justify-center font-bold text-sm flex-shrink-0">{i+1}</div>
                                    <div className="flex-1">
                                        <label className="text-xs font-bold text-gray-500 uppercase tracking-wider mb-1 block">{cat}</label>
                                        <input
                                            type="text"
                                            value={answers[i] || ''}
                                            onChange={e => setAnswers({...answers, [i]: e.target.value})}
                                            placeholder={`Un(e) ${cat.toLowerCase()} en ${letter}...`}
                                            className="w-full px-3 py-2 border-2 border-gray-200 rounded-lg focus:border-green-400 focus:outline-none text-sm"
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <button onClick={finishEarly} className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition flex items-center justify-center gap-2">
                        <i data-lucide="check-circle"></i>
                        {currentPlayerIdx + 1 < players.length ? `Terminer — joueur suivant` : 'Voir les résultats !'}
                    </button>
                </div>
            </div>
        );
    }

    if (screen === 'reveal') {
        // Calculate scores from allAnswers (already saved via nextPlayer)
        const finalScores = calcScores(allAnswers);
        const winner = Object.entries(finalScores).sort((a,b) => b[1] - a[1])[0];
        
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 p-4">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white rounded-3xl shadow-2xl p-6 mb-4">
                        <h1 className="text-3xl font-bold text-center text-gray-800 mb-2">Résultats 🏆</h1>
                        <p className="text-center text-gray-500 mb-6">Lettre : <strong className="text-green-600 text-xl">{letter}</strong></p>

                        {/* Scores */}
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-8">
                            {Object.entries(finalScores).sort((a,b) => b[1]-a[1]).map(([name, score], i) => (
                                <div key={name} className={`rounded-2xl p-4 text-center ${i === 0 ? 'bg-yellow-100 ring-2 ring-yellow-400' : 'bg-gray-50'}`}>
                                    <div className="text-2xl mb-1">{i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉'}</div>
                                    <p className="font-bold text-gray-800 truncate">{name}</p>
                                    <p className={`text-3xl font-black ${i === 0 ? 'text-yellow-600' : 'text-gray-600'}`}>{score}</p>
                                    <p className="text-xs text-gray-400">pts</p>
                                </div>
                            ))}
                        </div>

                        {/* Tableau comparatif */}
                        <div className="overflow-x-auto rounded-xl border border-gray-100">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="bg-gray-50">
                                        <th className="p-3 text-left font-bold text-gray-600">Catégorie</th>
                                        {allAnswers.map((fa, i) => (
                                            <th key={i} className="p-3 text-center font-bold text-gray-600">{fa.playerName}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {CATEGORIES.map((cat, catIdx) => {
                                        const catAnswers = allAnswers.map(fa => (fa.answers[catIdx] || '').trim().toLowerCase());
                                        return (
                                            <tr key={catIdx} className="border-t border-gray-100">
                                                <td className="p-3 font-semibold text-gray-700 bg-gray-50">{cat}</td>
                                                {allAnswers.map((fa, pIdx) => {
                                                    const ans = fa.answers[catIdx] || '';
                                                    const valid = ans && ans[0]?.toUpperCase() === letter;
                                                    const dup = valid && catAnswers.filter(a => a === ans.toLowerCase().trim()).length > 1;
                                                    return (
                                                        <td key={pIdx} className={`p-3 text-center ${valid ? (dup ? 'text-orange-600 font-semibold' : 'text-green-600 font-semibold') : 'text-gray-400'}`}>
                                                            {ans || '—'}
                                                            {valid && <span className="ml-1 text-xs">({dup ? '5' : '10'}pts)</span>}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div className="flex gap-3">
                        <button onClick={startGame} className="flex-1 bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition">Rejouer</button>
                        <button onClick={onBack} className="flex-1 bg-white text-gray-600 font-bold py-4 rounded-xl shadow hover:bg-gray-50 transition">Retour</button>
                    </div>
                </div>
            </div>
        );
    }

    return null;
};

            // ========== JEU PICTIONARY ==========
const PictionaryGame = ({ onBack }) => {
    const WORDS_BY_CATEGORY = {
        'Animaux': ['Éléphant', 'Girafe', 'Pingouin', 'Crocodile', 'Papillon', 'Dauphin', 'Koala', 'Flamant rose', 'Tortue', 'Perroquet'],
        'Objets': ['Parapluie', 'Téléphone', 'Vélo', 'Chapeau', 'Lunettes', 'Brosse à dents', 'Ballon', 'Clé', 'Livre', 'Sac à dos'],
        'Actions': ['Courir', 'Danser', 'Nager', 'Grimper', 'Dormir', 'Rire', 'Chanter', 'Manger', 'Lire', 'Peindre'],
        'Lieux': ['Plage', 'Montagne', 'Supermarché', 'Hôpital', 'École', 'Forêt', 'Château', 'Désert', 'Île', 'Aéroport'],
        'Nourriture': ['Pizza', 'Sushi', 'Glace', 'Gâteau', 'Raisin', 'Ananas', 'Hamburger', 'Spaghetti', 'Crêpe', 'Baguette'],
        'BridgeLink': ['Famille', 'Souvenir', 'Câlin', 'Vacances', 'Jeux', 'Rire', 'Fête', 'Album photo', 'Pique-nique', 'Surprise']
    };

    const [screen, setScreen] = useState('setup'); // setup, category, drawing, guessing, reveal, scores
    const [players, setPlayers] = useState([{name:''},{name:''}]);
    const [setupDone, setSetupDone] = useState(false);
    const [currentDrawerIdx, setCurrentDrawerIdx] = useState(0);
    const [scores, setScores] = useState({});
    const [currentWord, setCurrentWord] = useState('');
    const [currentCategory, setCurrentCategory] = useState('');
    const [timeLeft, setTimeLeft] = useState(60);
    const [timerActive, setTimerActive] = useState(false);
    const [guessInput, setGuessInput] = useState('');
    const [guessResult, setGuessResult] = useState(null); // null | 'correct' | 'wrong'
    const [round, setRound] = useState(1);
    const [totalRounds] = useState(3);
    const [wordRevealed, setWordRevealed] = useState(false);
    const [canvasRef] = useState(() => React.createRef());
    const [isDrawing, setIsDrawing] = useState(false);
    const [lastPos, setLastPos] = useState(null);
    const [color, setColor] = useState('#1e40af');
    const [brushSize, setBrushSize] = useState(6);
    const [tool, setTool] = useState('pen'); // pen | eraser
    const [showWord, setShowWord] = useState(false);

    useEffect(() => { lucide.createIcons(); }, [screen, setupDone, showWord]);

    // Timer
    useEffect(() => {
        if (!timerActive) return;
        if (timeLeft <= 0) { setTimerActive(false); setScreen('reveal'); return; }
        const t = setTimeout(() => setTimeLeft(t => t - 1), 1000);
        return () => clearTimeout(t);
    }, [timerActive, timeLeft]);

    const drawerName = () => players[currentDrawerIdx]?.name || `Joueur ${currentDrawerIdx + 1}`;

    const pickWord = (cat) => {
        const words = WORDS_BY_CATEGORY[cat];
        return words[Math.floor(Math.random() * words.length)];
    };

    const startRound = (cat) => {
        const word = pickWord(cat);
        setCurrentCategory(cat);
        setCurrentWord(word);
        setGuessInput('');
        setGuessResult(null);
        setTimeLeft(60);
        setTimerActive(true);
        setWordRevealed(false);
        setShowWord(false);
        setScreen('drawing');
        // Clear canvas after mount
        setTimeout(() => {
            const canvas = canvasRef.current;
            if (canvas) {
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                ctx.fillStyle = '#fafafa';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
            }
        }, 50);
    };

    const handleGuess = () => {
        if (!guessInput.trim()) return;
        if (guessInput.trim().toLowerCase() === currentWord.toLowerCase()) {
            setGuessResult('correct');
            setTimerActive(false);
            // Give points
            const guesserName = players.find((p, i) => i !== currentDrawerIdx)?.name || 'Joueur';
            const dName = drawerName();
            setScores(prev => ({
                ...prev,
                [guesserName]: (prev[guesserName] || 0) + Math.max(10, timeLeft),
                [dName]: (prev[dName] || 0) + 5
            }));
            setTimeout(() => setScreen('reveal'), 1500);
        } else {
            setGuessResult('wrong');
            setTimeout(() => setGuessResult(null), 800);
        }
        setGuessInput('');
    };

    const nextTurn = () => {
        const nextIdx = (currentDrawerIdx + 1) % players.length;
        const newRound = nextIdx === 0 ? round + 1 : round;
        if (newRound > totalRounds) {
            setScreen('scores');
        } else {
            setCurrentDrawerIdx(nextIdx);
            setRound(newRound);
            setScreen('category');
        }
    };

    // Canvas drawing
    const getPos = (e, canvas) => {
        const rect = canvas.getBoundingClientRect();
        const scaleX = canvas.width / rect.width;
        const scaleY = canvas.height / rect.height;
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return { x: (clientX - rect.left) * scaleX, y: (clientY - rect.top) * scaleY };
    };

    const startDraw = (e) => {
        e.preventDefault();
        const canvas = canvasRef.current;
        if (!canvas) return;
        const pos = getPos(e, canvas);
        setIsDrawing(true);
        setLastPos(pos);
        const ctx = canvas.getContext('2d');
        ctx.beginPath();
        ctx.arc(pos.x, pos.y, (tool === 'eraser' ? 20 : brushSize) / 2, 0, Math.PI * 2);
        ctx.fillStyle = tool === 'eraser' ? '#fafafa' : color;
        ctx.fill();
    };

    const draw = (e) => {
        e.preventDefault();
        if (!isDrawing) return;
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        const pos = getPos(e, canvas);
        ctx.beginPath();
        ctx.moveTo(lastPos.x, lastPos.y);
        ctx.lineTo(pos.x, pos.y);
        ctx.strokeStyle = tool === 'eraser' ? '#fafafa' : color;
        ctx.lineWidth = tool === 'eraser' ? 30 : brushSize;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.stroke();
        setLastPos(pos);
    };

    const endDraw = () => setIsDrawing(false);

    const clearCanvas = () => {
        const canvas = canvasRef.current;
        if (!canvas) return;
        const ctx = canvas.getContext('2d');
        ctx.fillStyle = '#fafafa';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    };

    const COLORS = ['#1e40af','#dc2626','#16a34a','#ea580c','#7c3aed','#0891b2','#db2777','#92400e','#000000'];

    if (!setupDone) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-orange-50 to-pink-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
                    <button onClick={onBack} className="flex items-center gap-2 text-gray-600 font-bold hover:text-orange-600 mb-6">
                        <i data-lucide="arrow-left"></i> Retour
                    </button>
                    <div className="text-center mb-8">
                        <div className="text-6xl mb-3">🎨</div>
                        <h1 className="text-3xl font-extrabold text-gray-800 mb-2">Pictionary</h1>
                        <p className="text-gray-500">Dessinez et faites deviner !</p>
                    </div>
                    <div className="space-y-3 mb-6">
                        {players.map((p, i) => (
                            <div key={i} className="flex gap-3 items-center">
                                <div className="bg-orange-100 text-orange-700 font-bold w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 text-sm">{i+1}</div>
                                <input
                                    type="text"
                                    value={p.name}
                                    onChange={e => { const np = [...players]; np[i].name = e.target.value; setPlayers(np); }}
                                    placeholder={`Prénom joueur ${i+1}`}
                                    className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-orange-400 focus:outline-none"
                                    maxLength={20}
                                />
                                {players.length > 2 && (
                                    <button onClick={() => setPlayers(players.filter((_, idx) => idx !== i))} className="text-red-400 hover:text-red-600 font-bold text-xl">×</button>
                                )}
                            </div>
                        ))}
                    </div>
                    {players.length < 6 && (
                        <button onClick={() => setPlayers([...players, {name:''}])} className="w-full border-2 border-dashed border-orange-300 text-orange-600 font-semibold py-3 rounded-xl hover:bg-orange-50 transition mb-4">
                            + Ajouter un joueur
                        </button>
                    )}
                    <button
                        onClick={() => { setSetupDone(true); const sc = {}; players.forEach((p,i) => { sc[p.name || `Joueur ${i+1}`] = 0; }); setScores(sc); setScreen('category'); }}
                        className="w-full bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition flex items-center justify-center gap-2"
                    >
                        <i data-lucide="play"></i> C'est parti !
                    </button>
                </div>
            </div>
        );
    }

    if (screen === 'category') {
        return (
            <div className="min-h-screen bg-gradient-to-br from-orange-50 to-pink-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full">
                    <div className="bg-white rounded-3xl shadow-2xl p-8 text-center mb-4">
                        <div className="text-5xl mb-3">🎨</div>
                        <h2 className="text-2xl font-bold text-gray-800 mb-1">C'est à toi de dessiner !</h2>
                        <div className="bg-orange-100 text-orange-700 font-bold px-4 py-2 rounded-full inline-block mb-1">{drawerName()}</div>
                        <p className="text-gray-500 text-sm mb-6">Manche {round}/{totalRounds} — Choisissez une catégorie :</p>
                        <p className="text-xs text-gray-400 mb-4">⚠️ Seulement {drawerName()} regarde pour choisir !</p>
                        <div className="grid grid-cols-2 gap-3">
                            {Object.keys(WORDS_BY_CATEGORY).map(cat => (
                                <button key={cat} onClick={() => startRound(cat)} className="bg-gradient-to-br from-orange-100 to-pink-100 hover:from-orange-200 hover:to-pink-200 border-2 border-orange-200 hover:border-orange-400 text-gray-800 font-bold py-4 px-3 rounded-xl transition hover:scale-105">
                                    {cat}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="bg-white rounded-2xl shadow p-4 text-center">
                        <p className="text-sm text-gray-500">Scores actuels :</p>
                        <div className="flex flex-wrap gap-2 justify-center mt-2">
                            {Object.entries(scores).map(([n,s]) => (
                                <span key={n} className="bg-orange-100 text-orange-700 px-3 py-1 rounded-full text-sm font-bold">{n}: {s}pts</span>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    if (screen === 'drawing') {
        const timerColor = timeLeft > 30 ? 'text-green-600' : timeLeft > 10 ? 'text-orange-500' : 'text-red-600';
        return (
            <div className="min-h-screen bg-gradient-to-br from-orange-50 to-pink-50 p-3">
                <div className="max-w-2xl mx-auto">
                    {/* Header */}
                    <div className="bg-white rounded-2xl shadow-lg p-3 mb-3 flex items-center justify-between gap-3">
                        <div>
                            <p className="text-xs text-gray-500">Dessinateur</p>
                            <p className="font-bold text-gray-800">{drawerName()}</p>
                        </div>
                        {showWord ? (
                            <div className="bg-orange-500 text-white px-4 py-2 rounded-xl text-center flex-1">
                                <p className="text-xs opacity-80">Mot à dessiner</p>
                                <p className="text-xl font-black">{currentWord}</p>
                                <p className="text-xs opacity-70">{currentCategory}</p>
                            </div>
                        ) : (
                            <button onClick={() => setShowWord(true)} className="bg-orange-100 text-orange-700 px-4 py-2 rounded-xl font-bold hover:bg-orange-200 transition flex-1">
                                👁 Voir mon mot
                            </button>
                        )}
                        <div className={`text-center`}>
                            <p className="text-xs text-gray-500">Temps</p>
                            <p className={`text-2xl font-black ${timerColor}`}>{timeLeft}s</p>
                        </div>
                    </div>

                    {/* Canvas */}
                    <div className="bg-white rounded-2xl shadow-lg p-2 mb-3">
                        <canvas
                            ref={canvasRef}
                            width={600} height={380}
                            style={{ width: '100%', borderRadius: '12px', background: '#fafafa', cursor: tool === 'eraser' ? 'cell' : 'crosshair', touchAction: 'none' }}
                            onMouseDown={startDraw} onMouseMove={draw} onMouseUp={endDraw} onMouseLeave={endDraw}
                            onTouchStart={startDraw} onTouchMove={draw} onTouchEnd={endDraw}
                        />
                    </div>

                    {/* Tools */}
                    <div className="bg-white rounded-2xl shadow-lg p-3 mb-3">
                        <div className="flex items-center gap-3 flex-wrap">
                            {/* Colors */}
                            <div className="flex gap-1 flex-wrap">
                                {COLORS.map(c => (
                                    <button key={c} onClick={() => { setColor(c); setTool('pen'); }} style={{ background: c }} className={`w-7 h-7 rounded-full border-2 transition ${color === c && tool === 'pen' ? 'border-gray-800 scale-125' : 'border-transparent'}`} />
                                ))}
                            </div>
                            <div className="h-8 w-px bg-gray-200"></div>
                            {/* Brush sizes */}
                            <div className="flex gap-2 items-center">
                                {[4, 8, 14].map(s => (
                                    <button key={s} onClick={() => { setBrushSize(s); setTool('pen'); }} style={{ width: s+8, height: s+8, background: brushSize === s && tool === 'pen' ? '#1e40af' : '#d1d5db', borderRadius: '50%' }} className="transition hover:scale-110" />
                                ))}
                            </div>
                            <div className="h-8 w-px bg-gray-200"></div>
                            <button onClick={() => setTool(tool === 'eraser' ? 'pen' : 'eraser')} className={`px-3 py-1.5 rounded-xl font-bold text-sm transition ${tool === 'eraser' ? 'bg-red-100 text-red-600 ring-2 ring-red-400' : 'bg-gray-100 text-gray-600'}`}>Gomme</button>
                            <button onClick={clearCanvas} className="px-3 py-1.5 rounded-xl font-bold text-sm bg-gray-100 text-gray-600 hover:bg-gray-200">Effacer tout</button>
                        </div>
                    </div>

                    {/* Guess zone for others */}
                    <div className="bg-white rounded-2xl shadow-lg p-4">
                        <p className="text-sm font-bold text-gray-600 mb-2">Les autres joueurs devinent :</p>
                        <div className="flex gap-2">
                            <input
                                type="text"
                                value={guessInput}
                                onChange={e => setGuessInput(e.target.value)}
                                onKeyDown={e => e.key === 'Enter' && handleGuess()}
                                placeholder="Votre réponse..."
                                className={`flex-1 px-4 py-3 border-2 rounded-xl focus:outline-none transition ${guessResult === 'wrong' ? 'border-red-400 bg-red-50' : guessResult === 'correct' ? 'border-green-400 bg-green-50' : 'border-gray-200 focus:border-orange-400'}`}
                            />
                            <button onClick={handleGuess} className="bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold px-5 rounded-xl hover:scale-105 transition">
                                ✓
                            </button>
                        </div>
                        {guessResult === 'wrong' && <p className="text-red-500 text-sm mt-1 font-semibold">❌ Pas tout à fait...</p>}
                        {guessResult === 'correct' && <p className="text-green-500 text-sm mt-1 font-semibold">✅ Bravo !</p>}
                        <button onClick={() => { setTimerActive(false); setScreen('reveal'); }} className="w-full mt-3 text-gray-400 text-sm hover:text-gray-600">Personne ne trouve → révéler le mot</button>
                    </div>
                </div>
            </div>
        );
    }

    if (screen === 'reveal') {
        return (
            <div className="min-h-screen bg-gradient-to-br from-orange-50 to-pink-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">
                    <div className="text-6xl mb-4">{guessResult === 'correct' ? '🎉' : '😅'}</div>
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">{guessResult === 'correct' ? 'Bonne réponse !' : 'Temps écoulé !'}</h2>
                    <p className="text-gray-500 mb-2">Le mot était :</p>
                    <div className="bg-gradient-to-r from-orange-500 to-pink-500 text-white text-3xl font-black px-6 py-4 rounded-2xl mb-2">{currentWord}</div>
                    <p className="text-gray-400 text-sm mb-8">Catégorie : {currentCategory}</p>
                    
                    {guessResult === 'correct' && (
                        <div className="bg-green-50 p-3 rounded-xl mb-6 text-sm">
                            <p className="text-green-700 font-semibold">Points gagnés :</p>
                            <p className="text-green-600">Dessinateur ({drawerName()}) : <strong>+5pts</strong></p>
                        </div>
                    )}

                    <p className="text-gray-600 font-semibold mb-4">
                        {round < totalRounds || (currentDrawerIdx + 1) < players.length ? `Manche ${round}/${totalRounds}` : 'Dernière manche !'}
                    </p>
                    <button onClick={nextTurn} className="w-full bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition">
                        {(round >= totalRounds && (currentDrawerIdx + 1) >= players.length) ? 'Voir les scores finaux 🏆' : 'Tour suivant →'}
                    </button>
                </div>
            </div>
        );
    }

    if (screen === 'scores') {
        const sorted = Object.entries(scores).sort((a,b) => b[1]-a[1]);
        return (
            <div className="min-h-screen bg-gradient-to-br from-orange-50 to-pink-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8 text-center">
                    <div className="text-7xl mb-4">🏆</div>
                    <h1 className="text-3xl font-extrabold text-gray-800 mb-6">Scores finaux !</h1>
                    <div className="space-y-3 mb-8">
                        {sorted.map(([name, score], i) => (
                            <div key={name} className={`flex items-center justify-between rounded-2xl p-4 ${i === 0 ? 'bg-yellow-100 ring-2 ring-yellow-400' : 'bg-gray-50'}`}>
                                <div className="flex items-center gap-3">
                                    <span className="text-2xl">{i === 0 ? '🥇' : i === 1 ? '🥈' : '🥉'}</span>
                                    <span className="font-bold text-gray-800">{name}</span>
                                </div>
                                <span className={`text-2xl font-black ${i === 0 ? 'text-yellow-600' : 'text-gray-600'}`}>{score} pts</span>
                            </div>
                        ))}
                    </div>
                    {sorted.length > 0 && <p className="text-gray-500 mb-6">🎉 Bravo à <strong>{sorted[0][0]}</strong> !</p>}
                    <div className="flex gap-3">
                        <button onClick={() => { setSetupDone(false); setScreen('setup'); setRound(1); setCurrentDrawerIdx(0); setScores({}); }} className="flex-1 bg-gradient-to-r from-orange-500 to-pink-500 text-white font-bold py-4 rounded-xl transition hover:scale-[1.02]">Rejouer</button>
                        <button onClick={onBack} className="flex-1 bg-gray-100 text-gray-600 font-bold py-4 rounded-xl hover:bg-gray-200 transition">Retour</button>
                    </div>
                </div>
            </div>
        );
    }

    return null;
};

            // ========== ANCIEN JEU BACCALAURÉAT MULTIJOUEUR ==========
const BaccalaureatGameOld = ({ onBack }) => {
    const [screen, setScreen] = useState('menu'); // menu, waiting, playing, results
    const [gameData, setGameData] = useState(null);
    const [answers, setAnswers] = useState({});
    const [joinCode, setJoinCode] = useState('');
    const [opponentJoined, setOpponentJoined] = useState(false);
    const [finishedUsers, setFinishedUsers] = useState([]);
    const [results, setResults] = useState(null);

    useEffect(() => {
        lucide.createIcons();
    }, [screen, answers, results]);

    // Polling pour vérifier l'état du jeu
    useEffect(() => {
        if(!gameData || screen === 'menu' || screen === 'results') return;

        const interval = setInterval(async () => {
            try {
                const response = await fetch(`api/baccalaureat.php?action=check_status&game_id=${gameData.game_id}`);
                const data = await response.json();
                
                if(data.success){
                    setOpponentJoined(data.opponent_joined);
                    setFinishedUsers(data.finished_users);
                    
                    // Si l'adversaire a rejoint et qu'on attendait, passer en mode jeu
                    if(screen === 'waiting' && data.opponent_joined){
                        setScreen('playing');
                    }
                    
                    // Si les 2 ont terminé, afficher les résultats
                    if(data.finished_users.length === 2){
                        loadResults();
                    }
                }
            } catch(e){
                console.error('Erreur polling:', e);
            }
        }, 2000); // Vérifier toutes les 2 secondes

        return () => clearInterval(interval);
    }, [gameData, screen]);

    const createGame = async () => {
        try {
            const response = await fetch('api/baccalaureat.php?action=create_game');
            const data = await response.json();
            
            if(data.success){
                setGameData(data);
                setScreen('waiting');
                tracking.logEvent('BACCALAUREAT_CREATED', { code: data.game_code });
            }
        } catch(e) {
            alert('Erreur lors de la création de la partie');
        }
    };

    const joinGame = async () => {
        if(!joinCode.trim()){
            alert('Entrez un code de partie');
            return;
        }

        try {
            const response = await fetch(`api/baccalaureat.php?action=join_game&game_code=${joinCode.toUpperCase()}`);
            const data = await response.json();
            
            if(data.success){
                setGameData(data);
                setScreen('playing');
                tracking.logEvent('BACCALAUREAT_JOINED', { code: joinCode });
            } else {
                alert(data.error || 'Erreur');
            }
        } catch(e) {
            alert('Erreur lors de la connexion');
        }
    };

    const saveAnswers = async (isFinished = false) => {
        try {
            await fetch('api/baccalaureat.php?action=save_answers', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    game_id: gameData.game_id,
                    answers: answers,
                    is_finished: isFinished
                })
            });
        } catch(e) {
            console.error('Erreur sauvegarde:', e);
        }
    };

    const finishGame = async () => {
        await saveAnswers(true);
        tracking.logEvent('BACCALAUREAT_FINISHED');
    };

    const loadResults = async () => {
        try {
            const response = await fetch(`api/baccalaureat.php?action=get_results&game_id=${gameData.game_id}`);
            const data = await response.json();
            
            if(data.success){
                setResults(data);
                setScreen('results');
            }
        } catch(e) {
            console.error('Erreur chargement résultats:', e);
        }
    };

    // Auto-save toutes les 3 secondes pendant le jeu
    useEffect(() => {
        if(screen !== 'playing') return;

        const interval = setInterval(() => {
            saveAnswers(false);
        }, 3000);

        return () => clearInterval(interval);
    }, [screen, answers]);

    // Menu principal
    if(screen === 'menu'){
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 p-6">
                <div className="max-w-2xl mx-auto">
                    <button onClick={onBack} className="flex items-center gap-2 text-gray-600 font-bold hover:text-green-600 mb-8">
                        <i data-lucide="arrow-left"></i> Retour aux jeux
                    </button>

                    <div className="bg-white rounded-3xl shadow-2xl p-8 text-center mb-6">
                        <div className="bg-green-100 w-20 h-20 rounded-full flex items-center justify-center mx-auto mb-6">
                            <i data-lucide="book-open" className="w-10 h-10 text-green-600"></i>
                        </div>
                        <h1 className="text-4xl font-bold text-gray-800 mb-4">Baccalauréat</h1>
                        <p className="text-gray-600 text-lg">Jouez en temps réel avec votre famille !</p>
                    </div>

                    <div className="space-y-4">
                        <button
                            onClick={createGame}
                            className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-6 rounded-2xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-3"
                        >
                            <i data-lucide="plus-circle" className="w-6 h-6"></i>
                            Créer une partie
                        </button>

                        <div className="bg-white rounded-2xl shadow-lg p-6">
                            <h3 className="text-lg font-bold text-gray-800 mb-4">Rejoindre une partie</h3>
                            <div className="flex gap-3">
                                <input
                                    type="text"
                                    value={joinCode}
                                    onChange={(e) => setJoinCode(e.target.value.toUpperCase())}
                                    placeholder="CODE"
                                    maxLength={6}
                                    className="flex-1 px-4 py-3 border-2 border-gray-200 rounded-xl text-center font-bold text-2xl uppercase focus:border-green-500 focus:ring-0"
                                />
                                <button
                                    onClick={joinGame}
                                    className="bg-green-500 text-white font-bold px-6 rounded-xl hover:bg-green-600"
                                >
                                    Rejoindre
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="mt-8 bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl">
                        <p className="text-blue-800 font-medium text-sm">
                            <i data-lucide="info" className="w-4 h-4 inline mr-2"></i>
                            Remplissez 6 catégories avec des mots commençant par la lettre imposée. 10 points si unique, 5 points si doublon !
                        </p>
                    </div>
                </div>
            </div>
        );
    }

    // Salle d'attente
    if(screen === 'waiting'){
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 flex items-center justify-center p-6">
                <div className="bg-white rounded-3xl shadow-2xl p-8 max-w-md w-full text-center">
                    <div className="animate-pulse mb-6">
                        <i data-lucide="users" className="w-20 h-20 mx-auto text-green-500"></i>
                    </div>
                    
                    <h2 className="text-3xl font-bold text-gray-800 mb-4">En attente d'un adversaire...</h2>
                    
                    <div className="bg-green-50 p-6 rounded-2xl mb-6">
                        <p className="text-sm text-gray-600 mb-2">Code de la partie</p>
                        <p className="text-5xl font-bold text-green-600 tracking-wider">{gameData.game_code}</p>
                    </div>

                    <p className="text-gray-600 mb-2">Partagez ce code à votre adversaire</p>
                    <p className="text-sm text-gray-500">La partie commencera dès qu'il rejoindra</p>

                    <button
                        onClick={() => {
                            setScreen('menu');
                            setGameData(null);
                        }}
                        className="mt-6 text-gray-500 hover:text-gray-700 font-medium"
                    >
                        Annuler
                    </button>
                </div>
            </div>
        );
    }

    // Écran de jeu
    if(screen === 'playing'){
        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 p-6">
                <div className="max-w-3xl mx-auto">
                    <div className="bg-white rounded-3xl shadow-2xl p-8">
                        <div className="flex justify-between items-center mb-8">
                            <div className="bg-green-500 text-white px-6 py-3 rounded-2xl">
                                <p className="text-sm mb-1">Lettre</p>
                                <p className="text-5xl font-bold">{gameData.letter}</p>
                            </div>

                            <div className="text-right">
                                <p className="text-sm text-gray-500">Adversaire</p>
                                <p className={`font-bold ${opponentJoined ? 'text-green-600' : 'text-gray-400'}`}>
                                    {opponentJoined ? '✓ Connecté' : 'En attente...'}
                                </p>
                            </div>
                        </div>

                        <div className="space-y-4 mb-8">
                            {gameData.categories.map((category, index) => (
                                <div key={index} className="bg-gray-50 p-4 rounded-xl">
                                    <label className="block text-sm font-bold text-gray-700 mb-2">
                                        {category}
                                    </label>
                                    <input
                                        type="text"
                                        value={answers[index] || ''}
                                        onChange={(e) => setAnswers({...answers, [index]: e.target.value})}
                                        placeholder={`Un(e) ${category.toLowerCase()} en ${gameData.letter}...`}
                                        className="w-full px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-green-500 focus:ring-0"
                                    />
                                </div>
                            ))}
                        </div>

                        <button
                            onClick={finishGame}
                            className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl shadow-lg hover:shadow-xl transition-all flex items-center justify-center gap-2"
                        >
                            <i data-lucide="check-circle"></i>
                            J'ai terminé !
                        </button>

                        {finishedUsers.length > 0 && (
                            <div className="mt-4 text-center text-sm text-gray-600">
                                {finishedUsers.length === 1 ? 'Un joueur a terminé...' : 'Calcul des résultats...'}
                            </div>
                        )}
                    </div>
                </div>
            </div>
        );
    }

    // Écran des résultats
    if(screen === 'results' && results){
        const myUserId = Object.keys(results.players).find(uid => 
            results.players[uid].username === '<?php echo $_SESSION['user_identifiant']; ?>' // Vous devrez adapter ça
        );

        return (
            <div className="min-h-screen bg-gradient-to-br from-green-50 to-teal-50 p-6">
                <div className="max-w-4xl mx-auto">
                    <div className="bg-white rounded-3xl shadow-2xl p-8 mb-6">
                        <h1 className="text-3xl font-bold text-center text-gray-800 mb-8">Résultats</h1>

                        <div className="grid md:grid-cols-2 gap-6 mb-8">
                            {Object.entries(results.players).map(([uid, player]) => (
                                <div key={uid} className={`p-6 rounded-2xl ${results.scores[uid] >= Math.max(...Object.values(results.scores)) ? 'bg-gradient-to-br from-yellow-100 to-orange-100 border-2 border-yellow-400' : 'bg-gray-50'}`}>
                                    <div className="flex items-center justify-between mb-4">
                                        <h3 className="text-xl font-bold text-gray-800">{player.username}</h3>
                                        <div className="text-3xl font-bold text-green-600">{results.scores[uid]} pts</div>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        {results.categories.map((category, index) => {
                                            const answer = player.answers[index] || '';
                                            const otherAnswers = Object.entries(results.players)
                                                .filter(([other_uid]) => other_uid !== uid)
                                                .map(([_, p]) => p.answers[index]);
                                            const isDuplicate = otherAnswers.some(a => a && a.toLowerCase().trim() === answer.toLowerCase().trim());
                                            
                                            return (
                                                <div key={index} className="flex justify-between text-sm">
                                                    <span className="text-gray-600">{category}:</span>
                                                    <span className={`font-bold ${answer ? (isDuplicate ? 'text-orange-600' : 'text-green-600') : 'text-gray-400'}`}>
                                                        {answer || '—'} {answer && (isDuplicate ? '(5pts)' : '(10pts)')}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>

                        <button
                            onClick={() => {
                                setScreen('menu');
                                setGameData(null);
                                setAnswers({});
                                setResults(null);
                            }}
                            className="w-full bg-gradient-to-r from-green-500 to-teal-500 text-white font-bold py-4 rounded-xl"
                        >
                            Nouvelle partie
                        </button>
                    </div>
                </div>
            </div>
        );
    }

    return null;
};

            // ========== JEU MORPION ==========
            const MorpionGame = ({ onBack }) => {
                const [board, setBoard] = useState(Array(9).fill(null));
                const [isXNext, setIsXNext] = useState(true);
                const [winner, setWinner] = useState(null);
                const [scores, setScores] = useState({ X: 0, O: 0, draws: 0 });

                useEffect(() => {
                    lucide.createIcons();
                    const savedScores = localStorage.getItem('morpion_scores');
                    if (savedScores) {
                        setScores(JSON.parse(savedScores));
                    }
                }, []);

                useEffect(() => {
                    localStorage.setItem('morpion_scores', JSON.stringify(scores));
                }, [scores]);

                const calculateWinner = (squares) => {
                    const lines = [
                        [0, 1, 2], [3, 4, 5], [6, 7, 8], // Lignes
                        [0, 3, 6], [1, 4, 7], [2, 5, 8], // Colonnes
                        [0, 4, 8], [2, 4, 6] // Diagonales
                    ];

                    for (let line of lines) {
                        const [a, b, c] = line;
                        if (squares[a] && squares[a] === squares[b] && squares[a] === squares[c]) {
                            return { winner: squares[a], line };
                        }
                    }

                    if (squares.every(square => square !== null)) {
                        return { winner: 'draw', line: null };
                    }

                    return null;
                };

                useEffect(() => {
                    const result = calculateWinner(board);
                    if (result) {
                        setWinner(result);
                        if (result.winner === 'X') {
                            setScores(prev => ({ ...prev, X: prev.X + 1 }));
                            tracking.logEvent('MORPION_WIN', { winner: 'X' });
                        } else if (result.winner === 'O') {
                            setScores(prev => ({ ...prev, O: prev.O + 1 }));
                            tracking.logEvent('MORPION_WIN', { winner: 'O' });
                        } else {
                            setScores(prev => ({ ...prev, draws: prev.draws + 1 }));
                            tracking.logEvent('MORPION_DRAW');
                        }
                    }
                }, [board]);

                const handleClick = (index) => {
                    if (board[index] || winner) return;

                    const newBoard = [...board];
                    newBoard[index] = isXNext ? 'X' : 'O';
                    setBoard(newBoard);
                    setIsXNext(!isXNext);
                    tracking.logEvent('MORPION_MOVE', { player: isXNext ? 'X' : 'O', position: index });
                };

                const resetGame = () => {
                    setBoard(Array(9).fill(null));
                    setIsXNext(true);
                    setWinner(null);
                    tracking.logEvent('MORPION_RESET');
                };

                const resetScores = () => {
                    if (confirm('Remettre les scores à zéro ?')) {
                        setScores({ X: 0, O: 0, draws: 0 });
                        localStorage.removeItem('morpion_scores');
                        tracking.logEvent('MORPION_SCORES_RESET');
                    }
                };

                const isWinningSquare = (index) => {
                    return winner && winner.line && winner.line.includes(index);
                };

                return (
                    <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 p-6">
                        <div className="max-w-4xl mx-auto">
                            {/* Header */}
                            <div className="flex justify-between items-center mb-8">
                                <button
                                    onClick={() => {
                                        onBack();
                                        tracking.logEvent('MORPION_BACK_CLICKED');
                                    }}
                                    data-track="morpion_back"
                                    className="flex items-center gap-2 text-gray-600 font-bold hover:text-blue-600 transition-colors"
                                >
                                    <i data-lucide="arrow-left"></i> Retour aux jeux
                                </button>
                                <h1 className="text-4xl font-extrabold text-gray-800">Morpion</h1>
                                <div className="w-32"></div>
                            </div>

                            <div className="grid md:grid-cols-3 gap-6">
                                {/* Scores */}
                                <div className="bg-white rounded-2xl shadow-xl p-6">
                                    <h3 className="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2">
                                        <i data-lucide="trophy" className="text-yellow-500"></i>
                                        Scores
                                    </h3>
                                    <div className="space-y-3">
                                        <div className="flex justify-between items-center p-3 bg-blue-50 rounded-xl">
                                            <span className="font-bold text-blue-600 text-2xl">❌ X</span>
                                            <span className="text-3xl font-extrabold text-blue-600">{scores.X}</span>
                                        </div>
                                        <div className="flex justify-between items-center p-3 bg-pink-50 rounded-xl">
                                            <span className="font-bold text-pink-600 text-2xl">⭕ O</span>
                                            <span className="text-3xl font-extrabold text-pink-600">{scores.O}</span>
                                        </div>
                                        <div className="flex justify-between items-center p-3 bg-gray-50 rounded-xl">
                                            <span className="font-bold text-gray-600">Égalités</span>
                                            <span className="text-2xl font-extrabold text-gray-600">{scores.draws}</span>
                                        </div>
                                    </div>
                                    <button
                                        onClick={resetScores}
                                        data-track="morpion_reset_scores"
                                        className="w-full mt-4 bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-2 rounded-xl transition-colors text-sm"
                                    >
                                        Réinitialiser les scores
                                    </button>
                                </div>

                                {/* Plateau de jeu */}
                                <div className="md:col-span-2">
                                    {/* Status */}
                                    <div className="bg-white rounded-2xl shadow-xl p-6 mb-6">
                                        {winner ? (
                                            <div className="text-center">
                                                {winner.winner === 'draw' ? (
                                                    <>
                                                        <i data-lucide="handshake" className="w-16 h-16 mx-auto text-gray-400 mb-3"></i>
                                                        <h2 className="text-3xl font-bold text-gray-600">Égalité !</h2>
                                                        <p className="text-gray-500 mt-2">Bien joué à tous les deux 👏</p>
                                                    </>
                                                ) : (
                                                    <>
                                                        <i data-lucide="trophy" className="w-16 h-16 mx-auto text-yellow-500 mb-3 animate-bounce"></i>
                                                        <h2 className="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-yellow-500 to-orange-500">
                                                            {winner.winner === 'X' ? '❌' : '⭕'} {winner.winner} gagne ! 🎉
                                                        </h2>
                                                        <p className="text-gray-600 mt-2">Félicitations ! 🌟</p>
                                                    </>
                                                )}
                                                <button
                                                    onClick={resetGame}
                                                    data-track="morpion_play_again"
                                                    className="mt-6 bg-gradient-to-r from-blue-500 to-purple-500 hover:from-blue-600 hover:to-purple-600 text-white font-bold py-3 px-8 rounded-xl transition-all shadow-lg"
                                                >
                                                    Rejouer
                                                </button>
                                            </div>
                                        ) : (
                                            <div className="text-center">
                                                <p className="text-gray-600 mb-2">Tour du joueur</p>
                                                <div className={`text-6xl font-bold ${isXNext ? 'text-blue-500' : 'text-pink-500'}`}>
                                                    {isXNext ? '❌' : '⭕'}
                                                </div>
                                                <p className="text-2xl font-bold text-gray-800 mt-2">
                                                    {isXNext ? 'X' : 'O'}
                                                </p>
                                            </div>
                                        )}
                                    </div>

                                    {/* Grille */}
                                    <div className="bg-white rounded-2xl shadow-xl p-6">
                                        <div className="grid grid-cols-3 gap-3 aspect-square">
                                            {board.map((value, index) => (
                                                <button
                                                    key={index}
                                                    onClick={() => handleClick(index)}
                                                    data-track={`morpion_cell_${index}`}
                                                    disabled={value !== null || winner !== null}
                                                    className={`
                                                        aspect-square rounded-xl text-6xl font-bold transition-all
                                                        ${value === null && !winner ? 'bg-gray-50 hover:bg-gray-100 hover:scale-105 cursor-pointer' : 'bg-gray-50'}
                                                        ${value === 'X' ? 'text-blue-500' : value === 'O' ? 'text-pink-500' : ''}
                                                        ${isWinningSquare(index) ? 'bg-gradient-to-br from-yellow-100 to-orange-100 ring-4 ring-yellow-400 scale-105' : ''}
                                                        ${value === null && !winner ? 'hover:shadow-md' : ''}
                                                        flex items-center justify-center
                                                    `}
                                                >
                                                    {value === 'X' ? '❌' : value === 'O' ? '⭕' : ''}
                                                </button>
                                            ))}
                                        </div>

                                        {!winner && (
                                            <button
                                                onClick={resetGame}
                                                data-track="morpion_restart"
                                                className="w-full mt-6 bg-gray-100 hover:bg-gray-200 text-gray-600 font-medium py-3 rounded-xl transition-colors flex items-center justify-center gap-2"
                                            >
                                                <i data-lucide="refresh-cw" className="w-4 h-4"></i>
                                                Recommencer
                                            </button>
                                        )}
                                    </div>
                                </div>
                            </div>

                            {/* Règles */}
                            <div className="mt-8 bg-blue-50 border-l-4 border-blue-400 p-6 rounded-xl">
                                <h3 className="text-xl font-bold text-gray-800 mb-3 flex items-center gap-2">
                                    <i data-lucide="info" className="text-blue-500"></i>
                                    Comment jouer ?
                                </h3>
                                <ul className="space-y-2 text-gray-700">
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500 font-bold">•</span>
                                        <span>Le joueur X commence toujours</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500 font-bold">•</span>
                                        <span>Cliquez sur une case vide pour placer votre symbole</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500 font-bold">•</span>
                                        <span>Le premier à aligner 3 symboles (horizontal, vertical ou diagonal) gagne !</span>
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-blue-500 font-bold">•</span>
                                        <span>Si toutes les cases sont remplies sans gagnant, c'est une égalité</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                );
            };
            
            // ========== JEU PUISSANCE 4 ==========
// Remplace (ou ajoute) ce composant dans ton index.php
// N'oublie pas d'ajouter { id: 'puissance4', ... } dans le tableau GAMES
// et de gérer activeGame === 'puissance4' dans le return de JeuxPage

const Puissance4Game = ({ onBack }) => {
    const ROWS = 6;
    const COLS = 7;
    const EMPTY = null;

    const emptyBoard = () => Array(ROWS).fill(null).map(() => Array(COLS).fill(EMPTY));

    const [board, setBoard] = useState(emptyBoard());
    const [currentPlayer, setCurrentPlayer] = useState(1);
    const [winner, setWinner] = useState(null); // null | 1 | 2 | 'draw'
    const [winningCells, setWinningCells] = useState([]);
    const [scores, setScores] = useState({ 1: 0, 2: 0 });
    const [playerNames, setPlayerNames] = useState({ 1: 'Joueur 1', 2: 'Joueur 2' });
    const [setupDone, setSetupDone] = useState(false);
    const [tempNames, setTempNames] = useState({ 1: '', 2: '' });
    const [hoverCol, setHoverCol] = useState(null);
    const [lastDrop, setLastDrop] = useState(null); // { row, col } pour animation

    useEffect(() => { lucide.createIcons(); }, [setupDone, winner, board]);

    // --- LOGIQUE GRAVITÉ ---
    // Retourne la ligne la plus basse disponible dans une colonne
    const getLowestRow = (b, col) => {
        for (let row = ROWS - 1; row >= 0; row--) {
            if (b[row][col] === EMPTY) return row;
        }
        return -1; // colonne pleine
    };

    // --- DÉTECTION VICTOIRE ---
    const checkWinner = (b, player) => {
        // Horizontal
        for (let r = 0; r < ROWS; r++) {
            for (let c = 0; c <= COLS - 4; c++) {
                if ([0,1,2,3].every(i => b[r][c+i] === player)) {
                    return [[r,c],[r,c+1],[r,c+2],[r,c+3]];
                }
            }
        }
        // Vertical
        for (let r = 0; r <= ROWS - 4; r++) {
            for (let c = 0; c < COLS; c++) {
                if ([0,1,2,3].every(i => b[r+i][c] === player)) {
                    return [[r,c],[r+1,c],[r+2,c],[r+3,c]];
                }
            }
        }
        // Diagonale \
        for (let r = 0; r <= ROWS - 4; r++) {
            for (let c = 0; c <= COLS - 4; c++) {
                if ([0,1,2,3].every(i => b[r+i][c+i] === player)) {
                    return [[r,c],[r+1,c+1],[r+2,c+2],[r+3,c+3]];
                }
            }
        }
        // Diagonale /
        for (let r = 3; r < ROWS; r++) {
            for (let c = 0; c <= COLS - 4; c++) {
                if ([0,1,2,3].every(i => b[r-i][c+i] === player)) {
                    return [[r,c],[r-1,c+1],[r-2,c+2],[r-3,c+3]];
                }
            }
        }
        return null;
    };

    const isDraw = (b) => b[0].every(cell => cell !== EMPTY);

    // --- JOUER UN COUP ---
    const dropToken = (col) => {
        if (winner) return;
        const row = getLowestRow(board, col);
        if (row === -1) return; // colonne pleine

        const newBoard = board.map(r => [...r]);
        newBoard[row][col] = currentPlayer;
        setBoard(newBoard);
        setLastDrop({ row, col });

        const winCells = checkWinner(newBoard, currentPlayer);
        if (winCells) {
            setWinner(currentPlayer);
            setWinningCells(winCells);
            setScores(prev => ({ ...prev, [currentPlayer]: prev[currentPlayer] + 1 }));
            tracking.logEvent('PUISSANCE4_WIN', { player: currentPlayer });
        } else if (isDraw(newBoard)) {
            setWinner('draw');
            tracking.logEvent('PUISSANCE4_DRAW');
        } else {
            setCurrentPlayer(currentPlayer === 1 ? 2 : 1);
        }
    };

    const resetGame = () => {
        setBoard(emptyBoard());
        setCurrentPlayer(1);
        setWinner(null);
        setWinningCells([]);
        setLastDrop(null);
        setHoverCol(null);
        tracking.logEvent('PUISSANCE4_RESET');
    };

    const isWinningCell = (r, c) => winningCells.some(([wr, wc]) => wr === r && wc === c);

    const p1Color = 'bg-red-500';
    const p2Color = 'bg-yellow-400';
    const p1ColorLight = 'bg-red-200';
    const p2ColorLight = 'bg-yellow-200';

    // --- ÉCRAN SETUP ---
    if (!setupDone) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
                    <div className="flex justify-between items-center mb-6">
                        <button onClick={() => { onBack(); }} className="flex items-center gap-2 text-gray-500 hover:text-indigo-600 transition-colors font-semibold">
                            <i data-lucide="arrow-left"></i> Retour
                        </button>
                    </div>

                    <div className="text-center mb-8">
                        <div className="text-6xl mb-3">🔴🟡</div>
                        <h1 className="text-3xl font-extrabold text-gray-800 mb-2">Puissance 4</h1>
                        <p className="text-gray-500">Entrez vos prénoms pour commencer !</p>
                    </div>

                    <div className="space-y-4 mb-8">
                        <div className="bg-red-50 rounded-2xl p-4">
                            <label className="block text-red-700 font-bold mb-2 flex items-center gap-2">
                                <span className="bg-red-500 text-white w-7 h-7 rounded-full flex items-center justify-center text-sm">🔴</span>
                                Joueur Rouge
                            </label>
                            <input
                                type="text"
                                placeholder="Prénom..."
                                value={tempNames[1]}
                                onChange={e => setTempNames({ ...tempNames, 1: e.target.value })}
                                className="w-full px-4 py-3 border-2 border-red-200 rounded-xl focus:border-red-400 focus:outline-none transition-colors"
                                maxLength={20}
                            />
                        </div>

                        <div className="bg-yellow-50 rounded-2xl p-4">
                            <label className="block text-yellow-700 font-bold mb-2 flex items-center gap-2">
                                <span className="bg-yellow-400 text-white w-7 h-7 rounded-full flex items-center justify-center text-sm">🟡</span>
                                Joueur Jaune
                            </label>
                            <input
                                type="text"
                                placeholder="Prénom..."
                                value={tempNames[2]}
                                onChange={e => setTempNames({ ...tempNames, 2: e.target.value })}
                                className="w-full px-4 py-3 border-2 border-yellow-200 rounded-xl focus:border-yellow-400 focus:outline-none transition-colors"
                                maxLength={20}
                            />
                        </div>
                    </div>

                    <button
                        onClick={() => {
                            setPlayerNames({
                                1: tempNames[1].trim() || 'Rouge',
                                2: tempNames[2].trim() || 'Jaune'
                            });
                            setSetupDone(true);
                            tracking.logEvent('PUISSANCE4_STARTED');
                        }}
                        className="w-full bg-gradient-to-r from-red-500 to-yellow-400 text-white font-bold py-4 rounded-xl shadow-lg transition-all hover:scale-[1.02] flex items-center justify-center gap-2 text-lg"
                    >
                        <i data-lucide="play"></i>
                        C'est parti !
                    </button>
                </div>
            </div>
        );
    }

    // --- JEU PRINCIPAL ---
    return (
        <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 p-4">
            <div className="max-w-lg mx-auto">

                {/* Header */}
                <div className="flex justify-between items-center mb-4">
                    <button onClick={() => { onBack(); }} className="flex items-center gap-2 text-gray-600 font-bold hover:text-indigo-600 transition-colors text-sm">
                        <i data-lucide="arrow-left"></i> Retour
                    </button>
                    <h1 className="text-2xl font-extrabold text-gray-800">Puissance 4</h1>
                    <button onClick={() => setSetupDone(false)} className="text-gray-400 hover:text-gray-600 text-sm font-semibold flex items-center gap-1">
                        <i data-lucide="settings" className="w-4 h-4"></i>
                    </button>
                </div>

                {/* Scores */}
                <div className="grid grid-cols-2 gap-3 mb-4">
                    <div className={`rounded-2xl p-3 transition-all duration-300 ${
                        currentPlayer === 1 && !winner
                            ? 'bg-red-500 text-white shadow-lg scale-105 ring-4 ring-red-300'
                            : 'bg-white shadow'
                    }`}>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className={`text-xs font-bold mb-0.5 ${currentPlayer === 1 && !winner ? 'text-red-100' : 'text-gray-400'}`}>
                                    {currentPlayer === 1 && !winner ? '🎯 Son tour' : '🔴 Rouge'}
                                </p>
                                <p className={`font-bold truncate ${currentPlayer === 1 && !winner ? 'text-white' : 'text-gray-700'}`}>
                                    {playerNames[1]}
                                </p>
                            </div>
                            <span className={`text-3xl font-extrabold ${currentPlayer === 1 && !winner ? 'text-white' : 'text-red-500'}`}>
                                {scores[1]}
                            </span>
                        </div>
                    </div>

                    <div className={`rounded-2xl p-3 transition-all duration-300 ${
                        currentPlayer === 2 && !winner
                            ? 'bg-yellow-400 text-white shadow-lg scale-105 ring-4 ring-yellow-200'
                            : 'bg-white shadow'
                    }`}>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className={`text-xs font-bold mb-0.5 ${currentPlayer === 2 && !winner ? 'text-yellow-100' : 'text-gray-400'}`}>
                                    {currentPlayer === 2 && !winner ? '🎯 Son tour' : '🟡 Jaune'}
                                </p>
                                <p className={`font-bold truncate ${currentPlayer === 2 && !winner ? 'text-white' : 'text-gray-700'}`}>
                                    {playerNames[2]}
                                </p>
                            </div>
                            <span className={`text-3xl font-extrabold ${currentPlayer === 2 && !winner ? 'text-white' : 'text-yellow-500'}`}>
                                {scores[2]}
                            </span>
                        </div>
                    </div>
                </div>

                {/* Indicateur de tour */}
                {!winner && (
                    <div className={`text-center py-2 px-4 rounded-xl mb-3 text-sm font-bold ${
                        currentPlayer === 1 ? 'bg-red-50 text-red-700' : 'bg-yellow-50 text-yellow-700'
                    }`}>
                        {currentPlayer === 1 ? '🔴' : '🟡'} À toi <strong>{playerNames[currentPlayer]}</strong> — appuie sur une colonne !
                    </div>
                )}

                {/* Grille - boutons colonnes (mobile: tap sur colonne) */}
                <div className="bg-indigo-700 rounded-2xl p-3 shadow-2xl">

                    {/* Flèches / indicateur de colonne survol (desktop) + boutons tap mobile */}
                    <div className="grid mb-2" style={{ gridTemplateColumns: `repeat(${COLS}, 1fr)`, gap: '6px' }}>
                        {Array(COLS).fill(null).map((_, col) => {
                            const full = getLowestRow(board, col) === -1;
                            return (
                                <button
                                    key={col}
                                    onClick={() => !winner && dropToken(col)}
                                    onMouseEnter={() => setHoverCol(col)}
                                    onMouseLeave={() => setHoverCol(null)}
                                    disabled={!!winner || full}
                                    className={`h-8 rounded-lg flex items-center justify-center transition-all ${
                                        full || winner
                                            ? 'opacity-0 cursor-default'
                                            : hoverCol === col
                                                ? currentPlayer === 1 ? 'bg-red-400' : 'bg-yellow-300'
                                                : 'bg-indigo-500 hover:bg-indigo-400'
                                    }`}
                                >
                                    {!full && !winner && (
                                        <div className={`w-4 h-4 rounded-full ${currentPlayer === 1 ? 'bg-red-300' : 'bg-yellow-200'} opacity-80`}></div>
                                    )}
                                </button>
                            );
                        })}
                    </div>

                    {/* Grille principale */}
                    <div className="grid" style={{ gridTemplateColumns: `repeat(${COLS}, 1fr)`, gap: '6px' }}>
                        {board.map((row, r) =>
                            row.map((cell, c) => {
                                const winning = isWinningCell(r, c);
                                const isLast = lastDrop && lastDrop.row === r && lastDrop.col === c;
                                return (
                                    <button
                                        key={`${r}-${c}`}
                                        onClick={() => !winner && dropToken(c)}
                                        className={`
                                            aspect-square rounded-full transition-all duration-200
                                            ${cell === null
                                                ? 'bg-indigo-900 hover:bg-indigo-800'
                                                : cell === 1
                                                    ? winning
                                                        ? 'bg-red-400 ring-4 ring-white scale-110 shadow-lg'
                                                        : isLast
                                                            ? 'bg-red-500 scale-105'
                                                            : 'bg-red-500'
                                                    : winning
                                                        ? 'bg-yellow-300 ring-4 ring-white scale-110 shadow-lg'
                                                        : isLast
                                                            ? 'bg-yellow-400 scale-105'
                                                            : 'bg-yellow-400'
                                            }
                                            ${hoverCol === c && cell === null && !winner ? 'brightness-125' : ''}
                                        `}
                                        style={{ minWidth: 0 }}
                                    />
                                );
                            })
                        )}
                    </div>
                </div>

                {/* Bouton recommencer */}
                <button
                    onClick={resetGame}
                    className="w-full mt-4 bg-white shadow hover:bg-gray-50 text-gray-700 font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2"
                >
                    <i data-lucide="refresh-cw" className="w-4 h-4"></i>
                    Nouvelle partie
                </button>

                {/* Règles */}
                <div className="mt-4 bg-white bg-opacity-70 p-4 rounded-xl text-sm text-gray-600">
                    <p className="font-bold text-gray-700 mb-1 flex items-center gap-2">
                        <i data-lucide="info" className="text-indigo-500 w-4 h-4"></i>
                        Comment jouer ?
                    </p>
                    Appuie sur une colonne pour laisser tomber ton jeton. Aligne <strong>4 jetons</strong> de ta couleur (horizontal, vertical ou diagonal) pour gagner ! 🏆
                </div>
            </div>

            {/* Modal victoire */}
            {winner && (
                <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50">
                    <div className="bg-white rounded-3xl shadow-2xl max-w-sm w-full p-8 text-center">
                        <div className="text-7xl mb-4">
                            {winner === 'draw' ? '🤝' : winner === 1 ? '🔴' : '🟡'}
                        </div>
                        <h2 className="text-3xl font-extrabold text-gray-800 mb-2">
                            {winner === 'draw'
                                ? 'Match nul !'
                                : `${playerNames[winner]} gagne !`
                            }
                        </h2>
                        <p className="text-gray-500 mb-6">
                            {winner === 'draw'
                                ? 'Personne n\'a réussi à aligner 4 jetons.'
                                : `Bravo pour cet alignement de 4 ! 🎉`
                            }
                        </p>

                        <div className="grid grid-cols-2 gap-3 mb-6">
                            <div className={`rounded-xl p-3 ${winner === 1 ? 'bg-red-100 ring-2 ring-red-400' : 'bg-gray-50'}`}>
                                <p className="text-sm font-bold text-gray-600">🔴 {playerNames[1]}</p>
                                <p className="text-2xl font-extrabold text-red-500">{scores[1]}</p>
                                <p className="text-xs text-gray-400">victoire{scores[1] > 1 ? 's' : ''}</p>
                            </div>
                            <div className={`rounded-xl p-3 ${winner === 2 ? 'bg-yellow-100 ring-2 ring-yellow-400' : 'bg-gray-50'}`}>
                                <p className="text-sm font-bold text-gray-600">🟡 {playerNames[2]}</p>
                                <p className="text-2xl font-extrabold text-yellow-500">{scores[2]}</p>
                                <p className="text-xs text-gray-400">victoire{scores[2] > 1 ? 's' : ''}</p>
                            </div>
                        </div>

                        <div className="flex gap-3">
                            <button
                                onClick={resetGame}
                                className="flex-1 bg-gradient-to-r from-red-500 to-yellow-400 text-white font-bold py-4 rounded-xl shadow-lg hover:scale-[1.02] transition-all"
                            >
                                Rejouer
                            </button>
                            <button
                                onClick={() => setSetupDone(false)}
                                className="flex-1 bg-gray-100 text-gray-600 font-bold py-4 rounded-xl hover:bg-gray-200 transition-all"
                            >
                                Changer joueurs
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
};

            // ========== JEU MEMORY (2 JOUEURS) ==========
// Remplace tout le bloc "const MemoryGame = ({ onBack }) => {" jusqu'à la fermeture "}" correspondante
// dans ton index.php

const MemoryGame = ({ onBack }) => {
    const [cards, setCards] = useState([]);
    const [flipped, setFlipped] = useState([]);
    const [matched, setMatched] = useState([]);
    const [moves, setMoves] = useState(0);
    const [isChecking, setIsChecking] = useState(false);
    const [gameWon, setGameWon] = useState(false);
    const [startTime, setStartTime] = useState(null);
    const [endTime, setEndTime] = useState(null);

    // --- 2 JOUEURS ---
    const [currentPlayer, setCurrentPlayer] = useState(1); // 1 ou 2
    const [scores, setScores] = useState({ 1: 0, 2: 0 });
    const [playerNames, setPlayerNames] = useState({ 1: 'Joueur 1', 2: 'Joueur 2' });
    const [setupDone, setSetupDone] = useState(false);
    const [tempNames, setTempNames] = useState({ 1: '', 2: '' });
    const [lastFoundBy, setLastFoundBy] = useState(null); // pour animation

    const EMOJIS = ['🐶', '🐱', '🐭', '🐹', '🐰', '🦊', '🐻', '🐼'];

    useEffect(() => {
        if (setupDone) initGame();
    }, [setupDone]);

    useEffect(() => {
        lucide.createIcons();
    }, [cards, flipped, matched, currentPlayer, setupDone]);

    const initGame = () => {
        const gameCards = [...EMOJIS, ...EMOJIS]
            .map((emoji, index) => ({ id: index, emoji, matched: false }))
            .sort(() => Math.random() - 0.5);

        setCards(gameCards);
        setFlipped([]);
        setMatched([]);
        setMoves(0);
        setGameWon(false);
        setStartTime(Date.now());
        setEndTime(null);
        setCurrentPlayer(1);
        setScores({ 1: 0, 2: 0 });
        setLastFoundBy(null);
        tracking.logEvent('MEMORY_GAME_STARTED_2P');
    };

    const handleCardClick = (index) => {
        if (isChecking || flipped.includes(index) || matched.includes(index) || flipped.length >= 2) return;

        const newFlipped = [...flipped, index];
        setFlipped(newFlipped);

        if (newFlipped.length === 2) {
            setMoves(moves + 1);
            setIsChecking(true);

            const [first, second] = newFlipped;
            if (cards[first].emoji === cards[second].emoji) {
                // Paire trouvée !
                const newMatched = [...matched, first, second];
                const newScores = { ...scores, [currentPlayer]: scores[currentPlayer] + 1 };
                setMatched(newMatched);
                setScores(newScores);
                setFlipped([]);
                setIsChecking(false);
                setLastFoundBy(currentPlayer);

                tracking.logEvent('MEMORY_MATCH_FOUND_2P', { player: currentPlayer });

                // Le joueur qui trouve rejoue !
                // Vérifier victoire
                if (newMatched.length === cards.length) {
                    setGameWon(true);
                    setEndTime(Date.now());
                    tracking.logEvent('MEMORY_GAME_WON_2P', {
                        scores: newScores,
                        moves: moves + 1
                    });
                }
            } else {
                // Pas de paire → on change de joueur
                setTimeout(() => {
                    setFlipped([]);
                    setIsChecking(false);
                    setCurrentPlayer(currentPlayer === 1 ? 2 : 1);
                    setLastFoundBy(null);
                }, 1000);
            }
        }
    };

    const formatTime = (ms) => {
        const seconds = Math.floor(ms / 1000);
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    };

    const getWinner = () => {
        if (scores[1] > scores[2]) return { name: playerNames[1], player: 1 };
        if (scores[2] > scores[1]) return { name: playerNames[2], player: 2 };
        return null; // égalité
    };

    // --- ÉCRAN DE CONFIGURATION ---
    if (!setupDone) {
        return (
            <div className="min-h-screen bg-gradient-to-br from-pink-50 to-purple-50 flex items-center justify-center p-6">
                <div className="max-w-md w-full bg-white rounded-3xl shadow-2xl p-8">
                    <div className="flex justify-between items-center mb-6">
                        <button
                            onClick={() => { onBack(); tracking.logEvent('MEMORY_BACK_CLICKED'); }}
                            className="flex items-center gap-2 text-gray-500 hover:text-pink-600 transition-colors font-semibold"
                        >
                            <i data-lucide="arrow-left"></i> Retour
                        </button>
                    </div>

                    <div className="text-center mb-8">
                        <div className="text-6xl mb-4">🃏</div>
                        <h1 className="text-3xl font-extrabold text-gray-800 mb-2">Memory</h1>
                        <p className="text-gray-500">Entrez vos prénoms pour commencer !</p>
                    </div>

                    <div className="space-y-4 mb-8">
                        {/* Joueur 1 */}
                        <div className="bg-blue-50 rounded-2xl p-4">
                            <label className="block text-blue-700 font-bold mb-2 flex items-center gap-2">
                                <span className="bg-blue-500 text-white w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">1</span>
                                Joueur 1
                            </label>
                            <input
                                type="text"
                                placeholder="Prénom du joueur 1..."
                                value={tempNames[1]}
                                onChange={e => setTempNames({ ...tempNames, 1: e.target.value })}
                                className="w-full px-4 py-3 border-2 border-blue-200 rounded-xl focus:border-blue-500 focus:outline-none transition-colors"
                                maxLength={20}
                            />
                        </div>

                        {/* Joueur 2 */}
                        <div className="bg-pink-50 rounded-2xl p-4">
                            <label className="block text-pink-700 font-bold mb-2 flex items-center gap-2">
                                <span className="bg-pink-500 text-white w-7 h-7 rounded-full flex items-center justify-center text-sm font-bold">2</span>
                                Joueur 2
                            </label>
                            <input
                                type="text"
                                placeholder="Prénom du joueur 2..."
                                value={tempNames[2]}
                                onChange={e => setTempNames({ ...tempNames, 2: e.target.value })}
                                className="w-full px-4 py-3 border-2 border-pink-200 rounded-xl focus:border-pink-500 focus:outline-none transition-colors"
                                maxLength={20}
                            />
                        </div>
                    </div>

                    <button
                        onClick={() => {
                            setPlayerNames({
                                1: tempNames[1].trim() || 'Joueur 1',
                                2: tempNames[2].trim() || 'Joueur 2'
                            });
                            setSetupDone(true);
                        }}
                        className="w-full bg-gradient-to-r from-pink-500 to-purple-500 hover:from-pink-600 hover:to-purple-600 text-white font-bold py-4 rounded-xl shadow-lg transition-all hover:scale-[1.02] flex items-center justify-center gap-2 text-lg"
                    >
                        <i data-lucide="play"></i>
                        Commencer la partie !
                    </button>
                </div>
            </div>
        );
    }

    // --- JEU PRINCIPAL ---
    return (
        <div className="min-h-screen bg-gradient-to-br from-pink-50 to-purple-50 p-4">
            <div className="max-w-4xl mx-auto">

                {/* Header */}
                <div className="flex justify-between items-center mb-4">
                    <button
                        onClick={() => { onBack(); tracking.logEvent('MEMORY_BACK_CLICKED'); }}
                        className="flex items-center gap-2 text-gray-600 font-bold hover:text-pink-600 transition-colors"
                    >
                        <i data-lucide="arrow-left"></i> Retour
                    </button>
                    <h1 className="text-3xl font-extrabold text-gray-800">Memory 🃏</h1>
                    <button
                        onClick={() => { setSetupDone(false); }}
                        className="flex items-center gap-2 text-gray-400 hover:text-gray-600 transition-colors text-sm font-semibold"
                    >
                        <i data-lucide="settings" className="w-4 h-4"></i> Joueurs
                    </button>
                </div>

                {/* Scores des 2 joueurs */}
                <div className="grid grid-cols-2 gap-3 mb-4">
                    {/* Joueur 1 */}
                    <div className={`rounded-2xl p-4 transition-all duration-300 ${
                        currentPlayer === 1
                            ? 'bg-blue-500 text-white shadow-lg scale-105 ring-4 ring-blue-300'
                            : 'bg-white text-gray-600 shadow'
                    }`}>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className={`text-xs font-bold uppercase tracking-wider mb-1 ${currentPlayer === 1 ? 'text-blue-100' : 'text-gray-400'}`}>
                                    {currentPlayer === 1 ? '🎯 Son tour !' : ''}
                                </p>
                                <p className={`font-bold text-lg truncate ${currentPlayer === 1 ? 'text-white' : 'text-gray-700'}`}>
                                    {playerNames[1]}
                                </p>
                            </div>
                            <div className={`text-4xl font-extrabold ${currentPlayer === 1 ? 'text-white' : 'text-blue-500'}`}>
                                {scores[1]}
                            </div>
                        </div>
                        <p className={`text-xs mt-1 ${currentPlayer === 1 ? 'text-blue-100' : 'text-gray-400'}`}>
                            paire{scores[1] > 1 ? 's' : ''} trouvée{scores[1] > 1 ? 's' : ''}
                        </p>
                    </div>

                    {/* Joueur 2 */}
                    <div className={`rounded-2xl p-4 transition-all duration-300 ${
                        currentPlayer === 2
                            ? 'bg-pink-500 text-white shadow-lg scale-105 ring-4 ring-pink-300'
                            : 'bg-white text-gray-600 shadow'
                    }`}>
                        <div className="flex items-center justify-between">
                            <div>
                                <p className={`text-xs font-bold uppercase tracking-wider mb-1 ${currentPlayer === 2 ? 'text-pink-100' : 'text-gray-400'}`}>
                                    {currentPlayer === 2 ? '🎯 Son tour !' : ''}
                                </p>
                                <p className={`font-bold text-lg truncate ${currentPlayer === 2 ? 'text-white' : 'text-gray-700'}`}>
                                    {playerNames[2]}
                                </p>
                            </div>
                            <div className={`text-4xl font-extrabold ${currentPlayer === 2 ? 'text-white' : 'text-pink-500'}`}>
                                {scores[2]}
                            </div>
                        </div>
                        <p className={`text-xs mt-1 ${currentPlayer === 2 ? 'text-pink-100' : 'text-gray-400'}`}>
                            paire{scores[2] > 1 ? 's' : ''} trouvée{scores[2] > 1 ? 's' : ''}
                        </p>
                    </div>
                </div>

                {/* Indicateur de tour + message */}
                <div className={`text-center py-3 px-4 rounded-xl mb-4 transition-all ${
                    lastFoundBy
                        ? 'bg-green-100 text-green-700'
                        : currentPlayer === 1
                            ? 'bg-blue-50 text-blue-700'
                            : 'bg-pink-50 text-pink-700'
                }`}>
                    {lastFoundBy ? (
                        <p className="font-bold">
                            🎉 Bravo <strong>{playerNames[lastFoundBy]}</strong> ! Paire trouvée, rejoue !
                        </p>
                    ) : (
                        <p className="font-bold">
                            ▶️ À toi <strong>{playerNames[currentPlayer]}</strong> — retourne 2 cartes !
                        </p>
                    )}
                </div>

                {/* Stat coups */}
                <div className="bg-white rounded-xl shadow px-4 py-2 flex items-center justify-center gap-4 mb-4 text-sm text-gray-500 font-semibold">
                    <span><i data-lucide="hand" className="w-4 h-4 inline mr-1"></i>{moves} coup{moves > 1 ? 's' : ''}</span>
                    <span>•</span>
                    <span><i data-lucide="check-circle" className="w-4 h-4 inline mr-1 text-green-500"></i>{matched.length / 2}/8 paires</span>
                </div>

                {/* Modal de victoire */}
                {gameWon && (() => {
                    const winner = getWinner();
                    return (
                        <div className="fixed inset-0 bg-black bg-opacity-60 flex items-center justify-center p-4 z-50">
                            <div className="bg-white rounded-3xl shadow-2xl max-w-md w-full p-8 text-center">
                                <div className="text-7xl mb-4">{winner ? '🏆' : '🤝'}</div>
                                <h2 className="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-yellow-500 to-orange-500 mb-2">
                                    {winner ? `${winner.name} gagne !` : 'Égalité !'}
                                </h2>
                                <p className="text-gray-500 mb-6">
                                    {winner
                                        ? `avec ${scores[winner.player]} paire${scores[winner.player] > 1 ? 's' : ''} trouvée${scores[winner.player] > 1 ? 's' : ''} 🎉`
                                        : `${scores[1]} paire${scores[1] > 1 ? 's' : ''} chacun — bien joué !`
                                    }
                                </p>

                                {/* Résumé des scores */}
                                <div className="grid grid-cols-2 gap-3 mb-6">
                                    <div className={`rounded-xl p-3 ${winner?.player === 1 ? 'bg-blue-100 ring-2 ring-blue-400' : 'bg-gray-50'}`}>
                                        <p className="font-bold text-gray-700 text-sm">{playerNames[1]}</p>
                                        <p className="text-2xl font-extrabold text-blue-600">{scores[1]}</p>
                                        <p className="text-xs text-gray-400">paires</p>
                                    </div>
                                    <div className={`rounded-xl p-3 ${winner?.player === 2 ? 'bg-pink-100 ring-2 ring-pink-400' : 'bg-gray-50'}`}>
                                        <p className="font-bold text-gray-700 text-sm">{playerNames[2]}</p>
                                        <p className="text-2xl font-extrabold text-pink-600">{scores[2]}</p>
                                        <p className="text-xs text-gray-400">paires</p>
                                    </div>
                                </div>

                                <div className="text-sm text-gray-400 mb-6">
                                    Partie terminée en <strong>{moves} coups</strong> • {formatTime(endTime - startTime)}
                                </div>

                                <div className="flex gap-3">
                                    <button
                                        onClick={initGame}
                                        className="flex-1 bg-gradient-to-r from-pink-500 to-purple-500 text-white font-bold py-4 rounded-xl transition-all hover:scale-[1.02] shadow-lg"
                                    >
                                        Rejouer
                                    </button>
                                    <button
                                        onClick={() => { setSetupDone(false); }}
                                        className="flex-1 bg-gray-100 text-gray-600 font-bold py-4 rounded-xl transition-all hover:bg-gray-200"
                                    >
                                        Changer joueurs
                                    </button>
                                </div>
                            </div>
                        </div>
                    );
                })()}

                {/* Grille de cartes */}
                <div className="bg-white rounded-3xl shadow-xl p-4">
                    <div className="grid grid-cols-4 gap-3">
                        {cards.map((card, index) => {
                            const isFlipped = flipped.includes(index) || matched.includes(index);
                            const isMatched = matched.includes(index);
                            return (
                                <button
                                    key={card.id}
                                    onClick={() => handleCardClick(index)}
                                    disabled={isFlipped || isChecking}
                                    className={`
                                        aspect-square text-4xl rounded-xl font-bold transition-all duration-300 transform
                                        flex items-center justify-center shadow-md
                                        ${isMatched
                                            ? 'bg-green-100 opacity-60 cursor-default scale-95'
                                            : isFlipped
                                                ? currentPlayer === 1
                                                    ? 'bg-gradient-to-br from-blue-400 to-blue-600 text-white scale-105 shadow-lg'
                                                    : 'bg-gradient-to-br from-pink-400 to-pink-600 text-white scale-105 shadow-lg'
                                                : 'bg-gradient-to-br from-gray-200 to-gray-300 hover:from-gray-300 hover:to-gray-400 hover:scale-105 cursor-pointer'
                                        }
                                    `}
                                >
                                    {isFlipped ? card.emoji : '❓'}
                                </button>
                            );
                        })}
                    </div>

                    <button
                        onClick={initGame}
                        className="w-full mt-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-3 rounded-xl transition-colors flex items-center justify-center gap-2"
                    >
                        <i data-lucide="refresh-cw" className="w-4 h-4"></i>
                        Recommencer
                    </button>
                </div>

                {/* Règles */}
                <div className="mt-4 bg-pink-50 border-l-4 border-pink-400 p-4 rounded-xl text-sm text-gray-600">
                    <p className="font-bold text-gray-700 mb-1 flex items-center gap-2">
                        <i data-lucide="info" className="text-pink-500 w-4 h-4"></i>
                        Règles à 2 joueurs
                    </p>
                    Retournez 2 cartes à tour de rôle. Si vous trouvez une paire, vous <strong>rejouez</strong> ! Celui qui a trouvé le plus de paires à la fin gagne. 🏆
                </div>
            </div>
        </div>
    );
};

            // ========== PAGE QUIZ ==========
            // ========== PAGE QUIZ ==========
const QuizPage = () => {
    const [screen, setScreen] = useState('themes');
    const [selectedTheme, setSelectedTheme] = useState(null);
    const [role, setRole] = useState(null);

    useEffect(() => {
        lucide.createIcons();
    }, [screen, role, selectedTheme]);

    // --- THÈMES DE QUIZ (22 thèmes) ---
    const QUIZ_THEMES = [
        { id: 'ecole', title: '🎓 École', description: '', color: 'blue', icon: 'graduation-cap' },
        { id: 'nourriture', title: '🍕 Nourriture', description: '', color: 'orange', icon: 'utensils' },
        { id: 'musique', title: '🎵 Musique', description: '', color: 'purple', icon: 'music' },
        { id: 'sport', title: '⚽ Sport', description: '', color: 'green', icon: 'trophy' },
        { id: 'animaux', title: '🐶 Animaux', description: '', color: 'yellow', icon: 'heart' },
        { id: 'voyages', title: '✈️ Voyages', description: '', color: 'cyan', icon: 'plane' },
        { id: 'cinema', title: '🎬 Cinéma', description: '', color: 'red', icon: 'film' },
        { id: 'lecture', title: '📚 Lecture', description: '', color: 'indigo', icon: 'book-open' },
        { id: 'loisirs', title: '🎨 Loisirs', description: '', color: 'pink', icon: 'palette' },
        { id: 'technologie', title: '💻 Réseaux sociaux', description: '', color: 'slate', icon: 'laptop' },
        { id: 'famille', title: '👨‍👩‍👧 Famille', description: '', color: 'rose', icon: 'users' },
        { id: 'fetes', title: '🎉 Fêtes', description: '', color: 'fuchsia', icon: 'sparkles' },
        { id: 'nature', title: '🌳 Nature', description: '', color: 'lime', icon: 'tree-pine' },
        { id: 'quotidien', title: '🏠 Quotidien', description: '', color: 'sky', icon: 'home' },
    ];

    // --- QUESTIONS PAR THÈME ---
    const QUESTIONS_BY_THEME = {
        ecole: {
            parent: [
                { id: 1, type: 'text', question: "Quelle est la matière préférée de votre enfant ?", feedback: "L'école forge les passions !" },
                { id: 2, type: 'text', question: "Qui est son meilleur ami à l'école ?", feedback: "Les amitiés d'enfance sont précieuses." },
                { id: 3, type: 'scale', question: "Sur une échelle de 1 à 5, aime-t-il aller à l'école ?", feedback: "Chaque enfant a son rapport à l'école." },
                { id: 4, type: 'text', question: "Quelle activité préfère-t-il à la récré ?", feedback: "La récré c'est important !" },
                { id: 5, type: 'text', question: "Comment l'aidez-vous pour les devoirs ?", feedback: "Le soutien scolaire compte." },
                { id: 6, type: 'text', question: "Quel est son plus grand défi à l'école ?", feedback: "Surmonter les défis le fait grandir." },
                { id: 7, type: 'text', question: "Quelle est sa note moyenne ?", feedback: "Les notes ne sont qu'un aspect." },
                { id: 8, type: 'multiple', question: "Que fait-il après l'école ?", options: ["Devoirs", "Joue dehors", "Regarde la télé", "Activités extra-scolaires", "Se repose"], feedback: "L'après-école structure la journée." },
                { id: 9, type: 'text', question: "Quel métier rêve-t-il de faire plus tard ?", feedback: "Les rêves d'enfant évoluent !" },
                { id: 10, type: 'text', question: "Quelle récompense lui donnez-vous pour un bon bulletin ?", feedback: "Célébrer les succès motive !" }
            ],
            child: [
                { id: 1, type: 'text', question: "Quelle matière ton parent préférait-il à l'école ?", feedback: "Nos parents aussi étaient élèves !" },
                { id: 2, type: 'text', question: "Comment ton parent t'aide-t-il pour les devoirs ?", feedback: "L'aide aux devoirs renforce les liens." },
                { id: 3, type: 'scale', question: "Sur une échelle de 1 à 5, ton parent était-il bon élève ?", feedback: "Nous avons tous notre parcours !" },
                { id: 4, type: 'text', question: "Que dit ton parent quand tu as une mauvaise note ?", feedback: "La réaction compte." },
                { id: 5, type: 'text', question: "Que dit ton parent quand tu as une bonne note ?", feedback: "Les encouragements sont importants." },
                { id: 6, type: 'text', question: "Est-ce que ton parent vient aux réunions parents-profs ?", feedback: "La présence compte." },
                { id: 7, type: 'text', question: "Quelle activité extra-scolaire ton parent voudrait que tu fasses ?", feedback: "Les activités enrichissent." },
                { id: 8, type: 'multiple', question: "Comment ton parent t'aide pour l'école ?", options: ["Vérifie les devoirs", "Explique", "M'encourage", "Parle au prof", "Achète des fournitures"], feedback: "Le soutien est essentiel !" },
                { id: 9, type: 'text', question: "Quel métier voulait faire ton parent quand il était petit ?", feedback: "Les rêves d'enfance..." },
                { id: 10, type: 'text', question: "Que fait ton parent si tu refuses d'aller à l'école ?", feedback: "Gérer les refus demande patience." }
            ]
        },
        nourriture: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le plat préféré de votre enfant ?", feedback: "Les goûts se forment tôt !" },
                { id: 2, type: 'text', question: "Quel aliment refuse-t-il absolument ?", feedback: "Chacun ses préférences." },
                { id: 3, type: 'text', question: "Quel est son dessert favori ?", feedback: "Les douceurs sucrées..." },
                { id: 4, type: 'text', question: "Que mange-t-il au petit-déjeuner ?", feedback: "Bien démarrer la journée." },
                { id: 5, type: 'scale', question: "Sur une échelle de 1 à 5, est-il difficile à table ?", feedback: "La patience aux repas !" },
                { id: 6, type: 'text', question: "Quel snack prend-il au goûter ?", feedback: "Le goûter est sacré !" },
                { id: 7, type: 'text', question: "Quel restaurant aime-t-il ?", feedback: "Sortir manger en famille." },
                { id: 8, type: 'multiple', question: "Où préfère-t-il manger ?", options: ["À la maison", "Au restaurant", "Chez Mamie", "Fast-food", "Pique-nique"], feedback: "Le lieu compte aussi !" },
                { id: 9, type: 'text', question: "Aime-t-il cuisiner avec vous ?", feedback: "Cuisiner ensemble crée des liens." },
                { id: 10, type: 'text', question: "Quelle boisson préfère-t-il ?", feedback: "L'hydratation c'est important !" },
                { id: 11, type: 'multiple', question: "Sucré ou salé? ?", options: ["Sucré", "Salé"], feedback: "Le fameux débat" }
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est le plat préféré de ton parent ?", feedback: "Tout le monde a son plat réconfort !" },
                { id: 2, type: 'text', question: "Que cuisine ton parent le mieux ?", feedback: "Les talents culinaires..." },
                { id: 3, type: 'scale', question: "Sur une échelle de 1 à 5, ton parent cuisine-t-il bien ?", feedback: "L'amour se met dans les plats !" },
                { id: 4, type: 'text', question: "Que mange ton parent au petit-déjeuner ?", feedback: "Bien commencer la journée." },
                { id: 5, type: 'text', question: "Quel dessert fait ton parent pour les fêtes ?", feedback: "Les desserts de fête..." },
                { id: 6, type: 'text', question: "Quel aliment ton parent n'aime pas ?", feedback: "Même les parents ont des goûts !" },
                { id: 7, type: 'text', question: "Quel restaurant ton parent préfère-t-il ?", feedback: "Sortir manger c'est sympa." },
                { id: 8, type: 'multiple', question: "Quand ton parent cuisine-t-il ?", options: ["Tous les jours", "Le weekend", "Les fêtes", "Rarement", "Jamais"], feedback: "Cuisiner demande du temps !" },
                { id: 9, type: 'text', question: "Est-ce que ton parent te laisse goûter en cuisinant ?", feedback: "Goûter c'est fun !" },
                { id: 10, type: 'text', question: "Quelle boisson ton parent boit le plus ?", feedback: "Café, thé ou eau ?" },
                { id: 11, type: 'multiple', question: "Sucré ou salé? ?", options: ["Sucré", "Salé"], feedback: "Le fameux débat" }
            ]
        },
        musique: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le genre préféré de votre enfant ?" },
                { id: 2, type: 'text', question: "Quel est sa musique du moment ?" },
                { id: 3, type: 'scale', question: "Votre enfant écoute souvent de la musique ?" },
                { id: 4, type: 'multiple', question: "Votre enfant préfère écouter avec un casque ou des écouteurs ?", options: ["Casque", "Ecouteurs", "Rien!"] },
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est le genre préféré de ton parent ?" },
                { id: 2, type: 'text', question: "Quel est sa musique du moment ?" },
                { id: 3, type: 'scale', question: "Ton parent écoute souvent de la musique ?" },
                { id: 4, type: 'multiple', question: "Ton parent préfère écouter avec un casque ou des écouteurs ?", options: ["Casque", "Ecouteurs", "Rien!"] },
                
            ]
        },
        sport: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le sport préféré de votre enfant ?" },
                { id: 2, type: 'scale', question: "Votre enfant aime-t-il/elle le spot ?" },
                { id: 3, type: 'multiple', question: "Votre enfant est-il/elle compétitif.ve ?", options: ["Oui", "Non"] },
                { id: 4, type: 'multiple', question: "Est-il/elle plutôt du genre à abandonner vite ou persévèrer ?", options: ["Abandonner", "Perséverer"] },
                { id: 5, type: 'multiple', question: "Préfere-t-il/elle les sports individuels ou collectifs ?", options: ["Individuels", "Collectifs", "Les deux"] },
                { id: 6, type: 'text', question: "Un sport que votre enfant déteste ?" },
                { id: 7, type: 'text', question: "Un sport que votre enfant aimerait essayer ?" },
                { id: 8, type: 'scale', question: "Votre enfant est-il/elle énergétique ?" },
            ],
            child: [
                { id: 1, type: 'scale', question: "Est-ce que ton parent pratique une activité physique régulièrement ?" },
                { id: 2, type: 'text', question: "Sport préféré de ton parent ?" },
                { id: 3, type: 'text', question: "Est-ce que ton parent regarde des compétitions sportives ?" },
                { id: 4, type: 'text', question: "Quel sport ton parent n'aime vraiment pas ?" },
                { id: 5, type: 'multiple', question: "Préfere-t-il/elle les sports individuels ou collectifs ?", options: ["Individuels", "Collectifs", "Les deux"] },
                { id: 6, type: 'text', question: "Quel sport pratiquait ton parent, plus jeune ?" },
                { id: 7, type: 'text', question: "Un sport que ton parent rêverait de tester ?" },
            ]
        },
        animaux: {
            parent: [
                { id: 1, type: 'text', question: "Quel est l'animal préféré de votre enfant ?" },
                { id: 2, type: 'text', question: "Votre enfant aimerait-il/elle avoir un animal ?" },
                { id: 3, type: 'text', question: "Votre enfant est-il/elle à l'aise avec les animaux ?" },
                { id: 4, type: 'text', question: "Un animal qui lui fait peur ?" },
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est l'animal préféré de ton parent ?" },
                { id: 2, type: 'text', question: "Ton parent a-t-il déjà eu un animal ?" },
                { id: 3, type: 'text', question: "Ton parent serait-il prêt à adopter un animal ?" },
                { id: 4, type: 'text', question: "Un animal qui lui fait peur ?" },
            ]
        },
        voyages: {
            parent: [
                { id: 1, type: 'scale', question: "Votre enfant aime-t-il/elle voyager ?" },
                { id: 2, type: 'text', question: "Quelle est sa destination de rêve ?" },
                { id: 3, type: 'text', question: "Quel a été son voyage préféré ?" },
                { id: 4, type: 'multiple', question: "Votre enfant est plutôt du genre à :", options: ["Improviser", "Tout planifier à l'avance"] },
                { id: 5, type: 'text', question: "Quel est son moyen de transport préféré ?"},
                { id: 6, type: 'scale', question: "Aime-t-il/elle les longs trajets ?"},
                { id: 7, type: 'multiple', question: "Votre enfant préfère :", options: ["Découvrir de nouveaux endroits", "Aller toujours au même endroit"] },
                { id: 8, type: 'text', question: "Votre enfant a-t-il/elle le mal des transports ?" },
            ],
            child: [
                { id: 1, type: 'scale', question: "Ton parent aime-t-il/elle voyager ?" },
                { id: 2, type: 'text', question: "Quelle est sa destination de rêve ?" },
                { id: 3, type: 'text', question: "Quel a été son voyage préféré ?" },
                { id: 4, type: 'multiple', question: "Ton parent est plutôt du genre à :", options: ["Improviser", "Tout planifier à l'avance"] },
                { id: 5, type: 'text', question: "Quel est son moyen de transport préféré ?"},
                { id: 6, type: 'scale', question: "Aime-t-il/elle les longs trajets ?"},
                { id: 7, type: 'multiple', question: "Ton parent préfère :", options: ["Découvrir de nouveaux endroits", "Aller toujours au même endroit"] },
                { id: 8, type: 'text', question: "Ton parent a-t-il/elle le mal des transports ?" },
            ]
        },
        cinema: {
            parent: [
                { id: 1, type: 'scale', question: "Votre enfant regarde-t-il/elle souvent des films et séries ?" },
                { id: 2, type: 'multiple', question: "Votre enfant préfère :", options: ["Films", "Séries", "Les deux"] },
                { id: 3, type: 'text', question: "Film ou série qu'il/elle déteste vraiment :" },
                { id: 4, type: 'text', question: "Quel est son film ou sa série préférée ?" },
                { id: 5, type: 'multiple', question: "Votre enfant préfère :", options: ["Comédie", "Action", "Romance", "Science-fiction"] },
                { id: 6, type: 'text', question: "Quel snack aime-t-il/elle manger en regardant un film ou une série ?" },
                { id: 7, type: 'multiple', question: "Votre enfant préfére :", options: ["Popcorn sucré", "Popcorn salé", "Aucun"] },
                { id: 8, type: 'text', question: "Quel film ou série lui conseillerez-vous ?" },
            ],
            child: [
                { id: 1, type: 'scale', question: "Ton parent regarde-t-il/elle souvent des films et séries ?" },
                { id: 2, type: 'multiple', question: "Ton parent préfère :", options: ["Films", "Séries", "Les deux"] },
                { id: 3, type: 'text', question: "Film ou série qu'il/elle déteste vraiment :" },
                { id: 4, type: 'text', question: "Quel est son film ou sa série préférée ?" },
                { id: 5, type: 'multiple', question: "Ton parent préfère :", options: ["Comédie", "Action", "Romance", "Science-fiction"] },
                { id: 6, type: 'text', question: "Quel snack aime-t-il/elle manger en regardant un film ou une série ?" },
                { id: 7, type: 'multiple', question: "Ton parent préfére :", options: ["Popcorn sucré", "Popcorn salé", "Aucun"] },
                { id: 8, type: 'text', question: "Quel film ou série lui conseillerais-tu ?" },
            ]
        },
        lecture: {
            parent: [
                { id: 1, type: 'scale', question: "Votre enfant aime lire ?" },
                { id: 2, type: 'text', question: "Quel genre de livre préfère-t-il/elle ?" },
                { id: 3, type: 'scale', question: "Votre enfant aime-t-il/elle lire avant de dormir ?" },
                { id: 4, type: 'multiple', question: "Votre enfant aime-t-il/elle :", options: ["Les BD", "Les mangas", "Aucun"] },
                { id: 5, type: 'text', question: "Votre enfant aime-t-il/elle écrire ?" },
                { id: 6, type: 'text', question: "Quel est son livre préféré ?" },
                { id: 7, type: 'text', question: "Quel livre lui conseillerez-vous ?" },
            ],
            child: [
                { id: 1, type: 'scale', question: "Ton parent aime lire ?" },
                { id: 2, type: 'text', question: "Quel genre de livre préfère-t-il/elle ?" },
                { id: 3, type: 'scale', question: "Ton parent aime-t-il/elle lire avant de dormir ?" },
                { id: 4, type: 'multiple', question: "Ton parent préfère :", options: ["Lire sur un écran", "Lire sur du papier"] },
                { id: 5, type: 'multiple', question: "Ton parent préfère-t-il :", options: ["Acheter des livres d'occasions", "Acheter des livres neufs"] },
                { id: 6, type: 'text', question: "Quel est son livre préféré ?" },
                { id: 7, type: 'text', question: "Quel livre lui conseillerais-tu ?" },
            ]
        },
        loisirs: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le hobby préféré de votre enfant ?", feedback: "Les loisirs nourrissent l'âme !" },
                { id: 2, type: 'multiple', question: "Votre enfant est plutôt :", options: ["Créatif / Artistique", "Manuel / Bricolage", "Technologique", "Sportif", "Curieux / Scientifique"], feedback: "Chacun ses talents !" },
                { id: 3, type: 'scale', question: "Votre enfant a-t-il facilement des idées pour s'occuper ?", feedback: "La créativité, ça s'entretient !" },
                { id: 4, type: 'text', question: "Quelle activité votre enfant pourrait faire pendant des heures ?", feedback: "La passion, c'est précieux." },
                { id: 5, type: 'multiple', question: "Votre enfant préfère ses loisirs :", options: ["Seul", "Avec des amis", "En famille", "Peu importe"], feedback: "Socialiser ou se ressourcer..." },
                { id: 6, type: 'text', question: "Quel talent caché a votre enfant ?", feedback: "Les talents cachés sont souvent les plus beaux." },
                { id: 7, type: 'text', question: "Quelle activité aimeriez-vous faire ensemble ?", feedback: "Les loisirs partagés créent des souvenirs." },
                { id: 8, type: 'scale', question: "Votre enfant est-il créatif ?", feedback: "La créativité se développe !" },
                { id: 9, type: 'multiple', question: "Votre enfant joue-t-il d'un instrument ?", options: ["Oui, régulièrement", "Oui, un peu", "Il aimerait", "Non"], feedback: "La musique, un langage universel." },
                { id: 10, type: 'text', question: "Quelle nouvelle activité voudriez-vous lui faire découvrir ?", feedback: "Explorer de nouveaux horizons !" }
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est le hobby préféré de ton parent ?", feedback: "On découvre souvent des passions insoupçonnées !" },
                { id: 2, type: 'multiple', question: "Ton parent est plutôt :", options: ["Créatif / Artistique", "Manuel / Bricolage", "Technologique", "Sportif", "Curieux / Scientifique"], feedback: "Chacun ses talents !" },
                { id: 3, type: 'scale', question: "Ton parent prend-il du temps pour ses loisirs ?", feedback: "Prendre soin de soi est important." },
                { id: 4, type: 'text', question: "Quelle activité ton parent faisait-il quand il était jeune ?", feedback: "Les passions d'enfance..." },
                { id: 5, type: 'text', question: "Quel talent caché a ton parent ?", feedback: "On ne connaît jamais tout de ses parents !" },
                { id: 6, type: 'text', question: "Quelle activité aimerais-tu faire avec ton parent ?", feedback: "Partager des loisirs c'est se connecter." },
                { id: 7, type: 'scale', question: "Ton parent est-il créatif ?", feedback: "La créativité prend mille formes." },
                { id: 8, type: 'multiple', question: "Ton parent joue-t-il d'un instrument ?", options: ["Oui, régulièrement", "Oui, un peu", "Il aimerait", "Non"], feedback: "La musique rapproche les gens." },
                { id: 9, type: 'text', question: "Quelle activité ton parent ne ferait jamais ?", feedback: "Les limites définissent aussi les gens !" },
                { id: 10, type: 'text', question: "Quelle nouvelle activité aimerais-tu lui faire découvrir ?", feedback: "Partager une passion, c'est un cadeau." }
            ]
        },
        technologie: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le réseau social préféré de votre enfant ?", feedback: "Le monde numérique d'aujourd'hui..." },
                { id: 2, type: 'scale', question: "Votre enfant passe-t-il trop de temps sur les écrans ?", feedback: "Le temps d'écran, un enjeu de notre époque." },
                { id: 3, type: 'multiple', question: "Votre enfant utilise surtout les réseaux pour :", options: ["Regarder des vidéos", "Parler avec des amis", "Jouer en ligne", "Créer du contenu", "Se tenir informé"], feedback: "Les usages varient beaucoup !" },
                { id: 4, type: 'text', question: "Quel compte ou créateur votre enfant suit-il ?", feedback: "Nos influences numériques nous façonnent." },
                { id: 5, type: 'scale', question: "Votre enfant est-il à l'aise avec la technologie ?", feedback: "La génération Z est très connectée." },
                { id: 6, type: 'text', question: "Quel appareil votre enfant utilise-t-il le plus ?", feedback: "Smartphone, tablette ou ordi ?" },
                { id: 7, type: 'multiple', question: "Votre enfant a-t-il un smartphone ?", options: ["Oui, depuis longtemps", "Oui, depuis peu", "Non, pas encore", "Non, pas du tout"], feedback: "L'accès au numérique évolue." },
                { id: 8, type: 'text', question: "Que regarde-t-il sur YouTube ou les réseaux ?", feedback: "Le contenu qu'on consomme nous influence." },
                { id: 9, type: 'scale', question: "Avez-vous des règles sur le temps d'écran à la maison ?", feedback: "Encadrer l'usage est sain." },
                { id: 10, type: 'multiple', question: "Votre enfant préfère :", options: ["TikTok", "YouTube", "Instagram", "Snapchat", "Aucun"], feedback: "Chaque plateforme a sa culture !" }
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est le réseau social préféré de ton parent ?", feedback: "Nos parents aussi ont leurs réseaux !" },
                { id: 2, type: 'scale', question: "Ton parent est-il à l'aise avec la technologie ?", feedback: "Les générations ont chacune leur rapport au numérique." },
                { id: 3, type: 'multiple', question: "Ton parent utilise surtout les réseaux pour :", options: ["Se tenir informé", "Parler avec la famille", "Regarder des vidéos", "Le travail", "Peu ou pas"], feedback: "Les usages générationnels diffèrent !" },
                { id: 4, type: 'text', question: "Ton parent passe-t-il beaucoup de temps sur son téléphone ?", feedback: "Le rapport aux écrans à tout âge..." },
                { id: 5, type: 'text', question: "Quel appareil ton parent utilise-t-il le plus ?", feedback: "Téléphone, ordinateur, tablette ?" },
                { id: 6, type: 'scale', question: "As-tu déjà dû aider ton parent avec la technologie ?", feedback: "Les rôles s'inversent parfois !" },
                { id: 7, type: 'text', question: "Qu'est-ce que ton parent ne comprend pas en technologie ?", feedback: "La technologie évolue très vite !" },
                { id: 8, type: 'multiple', question: "Ton parent est-il sur les réseaux sociaux ?", options: ["Oui, très actif", "Oui, un peu", "Rarement", "Non"], feedback: "La présence numérique générationnelle." },
                { id: 9, type: 'text', question: "As-tu déjà appris quelque chose de technologique à ton parent ?", feedback: "Apprendre à ses parents, c'est spécial." },
                { id: 10, type: 'multiple', question: "Ton parent préfère :", options: ["Facebook", "WhatsApp", "Instagram", "Aucun"], feedback: "Les réseaux de chaque génération !" }
            ]
        },
        famille: {
            parent: [
                { id: 1, type: 'text', question: "Quel est le meilleur souvenir en famille de votre enfant ?", feedback: "Les souvenirs forgent les liens." },
                { id: 2, type: 'scale', question: "Votre enfant est-il proche de ses grands-parents ?", feedback: "Le lien intergénérationnel est précieux." },
                { id: 3, type: 'multiple', question: "Votre enfant est-il plutôt :", options: ["Très proche de sa famille", "Indépendant", "Un mélange des deux"], feedback: "Chaque personnalité est unique." },
                { id: 4, type: 'text', question: "Quelle tradition familiale votre enfant préfère-t-il ?", feedback: "Les traditions créent l'identité." },
                { id: 5, type: 'text', question: "Qui dans la famille votre enfant admire-t-il le plus ?", feedback: "Les modèles familiaux sont forts." },
                { id: 6, type: 'scale', question: "Votre enfant parle-t-il facilement de ses émotions ?", feedback: "Exprimer ses émotions s'apprend." },
                { id: 7, type: 'multiple', question: "Face à une dispute, votre enfant :", options: ["S'énerve", "Pleure", "S'isole", "Essaie d'arranger", "Ignore"], feedback: "Les réactions émotionnelles varient." },
                { id: 8, type: 'text', question: "Quelle valeur familiale souhaitez-vous lui transmettre ?", feedback: "Les valeurs se transmettent de génération en génération." },
                { id: 9, type: 'text', question: "Ce que vous aimez le plus faire ensemble ?", feedback: "Ces moments-là comptent énormément." },
                { id: 10, type: 'scale', question: "Votre enfant est-il à l'aise pour parler de ses problèmes avec vous ?", feedback: "La confiance se construit jour après jour." }
            ],
            child: [
                { id: 1, type: 'text', question: "Quel est le meilleur souvenir en famille selon toi ?", feedback: "Les souvenirs partagés nous unissent." },
                { id: 2, type: 'scale', question: "Tu te sens proche de ta famille ?", feedback: "Le sentiment d'appartenance est important." },
                { id: 3, type: 'text', question: "Quelle tradition familiale préfères-tu ?", feedback: "Les traditions donnent un sens." },
                { id: 4, type: 'text', question: "Qui dans la famille ressemble le plus à ton parent ?", feedback: "La ressemblance familiale est fascinante." },
                { id: 5, type: 'scale', question: "Ton parent parle-t-il facilement de ses émotions ?", feedback: "L'expression émotionnelle, c'est aussi un modèle." },
                { id: 6, type: 'text', question: "Quelle valeur ton parent t'a-t-il transmise ?", feedback: "Les valeurs reçues nous construisent." },
                { id: 7, type: 'multiple', question: "Ton parent est-il plutôt :", options: ["Très présent", "Assez présent", "Parfois distant", "Occupé mais attentionné"], feedback: "La présence prend différentes formes." },
                { id: 8, type: 'text', question: "Ce que tu aimes le plus faire avec ta famille ?", feedback: "Ces moments font les meilleurs souvenirs." },
                { id: 9, type: 'text', question: "Qu'est-ce que tu aimerais faire plus souvent en famille ?", feedback: "Exprimer ses besoins, c'est courageux." },
                { id: 10, type: 'scale', question: "Tu te sens à l'aise pour parler de tes problèmes avec ton parent ?", feedback: "La confiance est la base de tout." }
            ]
        },
        fetes: {
            parent: [
                { id: 1, type: 'text', question: "Quelle est la fête préférée de votre enfant ?", feedback: "Les fêtes créent de la magie !" },
                { id: 2, type: 'multiple', question: "Pour son anniversaire, votre enfant préfère :", options: ["Une grande fête", "Quelques proches", "En famille seulement", "Une activité spéciale"], feedback: "Chaque anniversaire est unique." },
                { id: 3, type: 'text', question: "Quel cadeau a le plus marqué votre enfant ?", feedback: "Certains cadeaux restent gravés dans la mémoire." },
                { id: 4, type: 'scale', question: "Votre enfant est-il impatient avant les fêtes ?", feedback: "L'impatience fait partie du plaisir !" },
                { id: 5, type: 'text', question: "Quelle tradition de fête votre famille a-t-elle ?", feedback: "Les traditions de fêtes sont précieuses." },
                { id: 6, type: 'multiple', question: "Noël ou Anniversaire ?", options: ["Noël", "Anniversaire", "Les deux pareil"], feedback: "Le grand débat des fêtes !" },
                { id: 7, type: 'text', question: "Quel cadeau rêve de recevoir votre enfant ?", feedback: "Les listes de cadeaux révèlent beaucoup..." },
                { id: 8, type: 'scale', question: "Votre enfant aime-t-il faire des surprises aux autres ?", feedback: "Faire plaisir est un vrai talent." },
                { id: 9, type: 'text', question: "Quel gâteau d'anniversaire préfère-t-il ?", feedback: "Le gâteau d'anniversaire, moment culte !" },
                { id: 10, type: 'multiple', question: "Votre enfant préfère recevoir :", options: ["Un gros cadeau", "Plein de petits cadeaux", "Une surprise", "De l'argent"], feedback: "Chacun sa façon de recevoir !" }
            ],
            child: [
                { id: 1, type: 'text', question: "Quelle est la fête préférée de ton parent ?", feedback: "Les adultes adorent aussi les fêtes !" },
                { id: 2, type: 'multiple', question: "Pour son anniversaire, ton parent préfère :", options: ["Une grande fête", "Quelques proches", "En famille seulement", "Que ça passe inaperçu"], feedback: "L'anniversaire des adultes..." },
                { id: 3, type: 'text', question: "Quel cadeau as-tu offert à ton parent qu'il a adoré ?", feedback: "Offrir le cadeau parfait, c'est une joie." },
                { id: 4, type: 'scale', question: "Ton parent est-il enthousiaste pour préparer les fêtes ?", feedback: "Certains adorent préparer, d'autres subissent !" },
                { id: 5, type: 'text', question: "Quelle tradition de fête préfères-tu dans ta famille ?", feedback: "Les traditions nous rassemblent." },
                { id: 6, type: 'multiple', question: "Ton parent préfère offrir :", options: ["Des cadeaux utiles", "Des surprises", "De l'argent", "Des expériences", "Il hésite toujours"], feedback: "L'art d'offrir..." },
                { id: 7, type: 'text', question: "Quel souvenir de fête gardes-tu le plus précieusement ?", feedback: "Certains moments restent pour toujours." },
                { id: 8, type: 'scale', question: "Ton parent est-il bon pour garder les surprises secrètes ?", feedback: "Garder un secret... pas facile !" },
                { id: 9, type: 'text', question: "Quel gâteau d'anniversaire ton parent préfère-t-il ?", feedback: "Même les adultes adorent le gâteau !" },
                { id: 10, type: 'multiple', question: "Ton parent préfère recevoir :", options: ["Un gros cadeau", "Plein de petits cadeaux", "Une surprise", "De l'argent"], feedback: "Chacun sa façon de recevoir !" }
            ]
        },
        nature: {
            parent: [
                { id: 1, type: 'scale', question: "Votre enfant aime-t-il être dehors dans la nature ?", feedback: "Le contact avec la nature est essentiel." },
                { id: 2, type: 'multiple', question: "Votre enfant préfère :", options: ["La mer", "La montagne", "La forêt", "La campagne", "La ville"], feedback: "Nos paysages préférés nous définissent." },
                { id: 3, type: 'text', question: "Quelle activité en plein air votre enfant préfère ?", feedback: "La nature offre tant de possibilités !" },
                { id: 4, type: 'scale', question: "Votre enfant est-il sensible à l'environnement ?", feedback: "La conscience écologique se développe tôt." },
                { id: 5, type: 'multiple', question: "Votre enfant s'intéresse-t-il à :", options: ["Les insectes", "Les plantes", "Les roches/minéraux", "Les oiseaux", "Rien de spécial"], feedback: "La curiosité pour la nature..." },
                { id: 6, type: 'text', question: "Quel animal sauvage fascine votre enfant ?", feedback: "Les animaux sauvages captivent l'imagination !" },
                { id: 7, type: 'text', question: "Votre enfant aime-t-il jardiner ou planter ?", feedback: "Faire pousser quelque chose, c'est magique." },
                { id: 8, type: 'scale', question: "Votre enfant ramasse-t-il ses déchets en extérieur ?", feedback: "De bons réflexes dès le plus jeune âge." },
                { id: 9, type: 'text', question: "Quel endroit dans la nature votre enfant aimerait visiter ?", feedback: "Les rêves de nature..." },
                { id: 10, type: 'multiple', question: "Votre enfant préfère :", options: ["Les sorties nature en famille", "Rester à la maison", "Les deux selon l'humeur"], feedback: "Nature ou canapé, le grand dilemme !" }
            ],
            child: [
                { id: 1, type: 'scale', question: "Ton parent aime-t-il être dehors dans la nature ?", feedback: "Certains adultes adorent la nature." },
                { id: 2, type: 'multiple', question: "Ton parent préfère :", options: ["La mer", "La montagne", "La forêt", "La campagne", "La ville"], feedback: "Nos paysages de cœur..." },
                { id: 3, type: 'text', question: "Quelle activité en plein air ton parent préfère ?", feedback: "Randonnée, jardinage, plage ?" },
                { id: 4, type: 'scale', question: "Ton parent est-il sensible à l'environnement ?", feedback: "La conscience écologique générationnelle." },
                { id: 5, type: 'text', question: "Ton parent a-t-il un jardin ou des plantes chez lui ?", feedback: "Le pouce vert, ça se cultive !" },
                { id: 6, type: 'text', question: "Quel animal sauvage fascine ton parent ?", feedback: "Les animaux qui nous captivent..." },
                { id: 7, type: 'multiple', question: "Ton parent fait-il des gestes écologiques ?", options: ["Oui, beaucoup", "Quelques-uns", "Essaie d'en faire plus", "Pas vraiment"], feedback: "L'écologie du quotidien." },
                { id: 8, type: 'text', question: "Quel endroit dans la nature voudrais-tu visiter avec ton parent ?", feedback: "Les voyages nature sont mémorables." },
                { id: 9, type: 'scale', question: "Ton parent t'emmène-t-il souvent en plein air ?", feedback: "Partager la nature ensemble, c'est précieux." },
                { id: 10, type: 'multiple', question: "Ton parent préfère :", options: ["Les sorties nature en famille", "Rester à la maison", "Les deux selon l'humeur"], feedback: "Nature ou canapé ?" }
            ]
        },
        quotidien: {
            parent: [
                { id: 1, type: 'multiple', question: "Votre enfant est-il plutôt :", options: ["Du matin", "Du soir", "Ni l'un ni l'autre"], feedback: "Le rythme de chacun..." },
                { id: 2, type: 'scale', question: "Votre enfant range-t-il facilement sa chambre ?", feedback: "Le rangement... un classique entre parents et enfants !" },
                { id: 3, type: 'text', question: "Quelle corvée votre enfant déteste-t-il ?", feedback: "Les corvées, on a tous notre bête noire." },
                { id: 4, type: 'text', question: "Quelle corvée accepte-t-il le plus facilement ?", feedback: "Même les corvées ont leurs champions." },
                { id: 5, type: 'scale', question: "Votre enfant se lève-t-il facilement le matin ?", feedback: "La lutte matinale, un grand classique !" },
                { id: 6, type: 'text', question: "Quelle est sa routine du soir avant de dormir ?", feedback: "Les rituels du coucher sont importants." },
                { id: 7, type: 'multiple', question: "À table, votre enfant :", options: ["Mange de tout", "Est difficile", "Mange très vite", "Préfère grignoter", "Mange lentement"], feedback: "Chaque mangeur est unique !" },
                { id: 8, type: 'text', question: "Quelle est la première chose que fait votre enfant en rentrant de l'école ?", feedback: "Les rituels de retour à la maison." },
                { id: 9, type: 'scale', question: "Votre enfant est-il organisé au quotidien ?", feedback: "L'organisation s'apprend... parfois !" },
                { id: 10, type: 'text', question: "Quelle est la phrase que vous dites le plus souvent à votre enfant ?", feedback: "Ces phrases qui marquent une enfance." }
            ],
            child: [
                { id: 1, type: 'multiple', question: "Ton parent est-il plutôt :", options: ["Du matin", "Du soir", "Ni l'un ni l'autre"], feedback: "Le rythme circadien de chacun..." },
                { id: 2, type: 'scale', question: "Ton parent est-il ordonné à la maison ?", feedback: "L'ordre à la maison, un sujet sensible !" },
                { id: 3, type: 'text', question: "Quelle corvée ton parent déteste-t-il ?", feedback: "Même les parents ont leurs corvées préférées." },
                { id: 4, type: 'scale', question: "Ton parent se lève-t-il facilement le matin ?", feedback: "La matinalité, un talent ou un supplice !" },
                { id: 5, type: 'text', question: "Quelle est la première chose que fait ton parent le matin ?", feedback: "Les rituels matinaux révèlent beaucoup." },
                { id: 6, type: 'multiple', question: "Ton parent cuisine-t-il :", options: ["Tous les jours", "Souvent", "Parfois", "Rarement"], feedback: "Cuisiner au quotidien demande de l'énergie !" },
                { id: 7, type: 'text', question: "Quelle est la phrase que ton parent dit le plus souvent ?", feedback: "Ces phrases qui restent gravées à vie..." },
                { id: 8, type: 'scale', question: "Ton parent est-il stressé au quotidien ?", feedback: "Le stress du quotidien adulte." },
                { id: 9, type: 'text', question: "Quelle habitude de ton parent te surprend ou t'amuse ?", feedback: "Nos petites habitudes font partie de nous." },
                { id: 10, type: 'multiple', question: "Le soir, ton parent est plutôt :", options: ["Devant la télé", "Sur son téléphone", "À lire", "À discuter", "Vite au lit"], feedback: "Les soirées de chacun..." }
            ]
        },
        // ajouter les themes
    };

    const getQuestionsForTheme = (themeId) => {
        return QUESTIONS_BY_THEME[themeId] || {
            parent: [],
            child: []
        };
    };

    // --- API FUNCTIONS ---
    const saveAnswers = async (theme, role, answers) => {
        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'quiz',
                    theme: theme,
                    role: role,
                    answers: answers
                })
            });
            const data = await response.json();
            return data.success;
        } catch (e) {
            console.error("Erreur sauvegarde quiz:", e);
            return false;
        }
    };

    const getAnswers = async (theme, role) => {
        try {
            const response = await fetch(`api/load_data.php?type=quiz&theme=${theme}&role=${role}`);
            const data = await response.json();
            if(data.success && data.answers) {
                return { answers: data.answers, date: data.date };
            }
            return null;
        } catch (e) {
            console.error("Erreur chargement quiz:", e);
            return null;
        }
    };

    const getAllResults = async (theme) => {
        try {
            const response = await fetch(`api/load_shared_data.php?type=quiz&theme=${theme}&role=parent`);
            const dataParent = await response.json();
            
            const response2 = await fetch(`api/load_shared_data.php?type=quiz&theme=${theme}&role=child`);
            const dataChild = await response2.json();
            
            return {
                parent: dataParent.all_answers || [],
                child: dataChild.all_answers || []
            };
        } catch (e) {
            console.error("Erreur chargement résultats:", e);
            return { parent: [], child: [] };
        }
    };

    const deleteQuiz = async (theme) => {
        try {
            const response = await fetch('api/save_data.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'delete_quiz',
                    theme: theme
                })
            });
            return await response.json();
        } catch (e) {
            console.error("Erreur suppression quiz:", e);
            return { success: false };
        }
    };

    // Écran de sélection des thèmes
    const ThemesScreen = () => (
        <div className="min-h-screen bg-gradient-to-br from-indigo-100 via-purple-100 to-pink-100 p-6">
            <div className="max-w-7xl mx-auto">
                <div className="flex justify-between items-center mb-8">
                    <button onClick={() => { setActiveTab('accueil'); tracking.logEvent('QUIZ_BACK_TO_HOME'); }} data-track="quiz_back_home" className="flex items-center gap-2 text-gray-600 font-bold hover:text-purple-600 transition-colors">
                        <i data-lucide="arrow-left"></i> Retour à l'accueil
                    </button>
                    <h1 className="text-4xl font-extrabold text-transparent bg-clip-text bg-gradient-to-r from-purple-600 to-pink-600">Quiz Famille</h1>
                    <div className="w-32"></div>
                </div>

                <div className="bg-white rounded-3xl shadow-xl p-8 mb-8 text-center">
                    <i data-lucide="heart" className="w-16 h-16 mx-auto text-pink-500 mb-4"></i>
                    <h2 className="text-3xl font-bold text-gray-800 mb-3">Choisissez un thème</h2>
                    <p className="text-gray-600 text-lg">Apprenez à mieux vous connaître en répondant à des questions sur différents sujets !</p>
                    <p className="text-gray-600 text-lg"> D'autres thèmes arriveront bientôt </p>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    {QUIZ_THEMES.map(theme => (
                        <button key={theme.id} onClick={() => { setSelectedTheme(theme); setScreen('role-select'); tracking.logEvent('QUIZ_THEME_SELECTED', { theme: theme.id }); }} data-track={`quiz_theme_${theme.id}`} className="bg-white rounded-2xl shadow-lg hover:shadow-2xl transition-all p-6 text-center group hover:-translate-y-2">
                            <div className={`bg-${theme.color}-100 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-3 group-hover:scale-110 transition-transform`}>
                                <i data-lucide={theme.icon} className={`w-8 h-8 text-${theme.color}-600`}></i>
                            </div>
                            <h3 className="text-lg font-bold text-gray-800 mb-2">{theme.title}</h3>
                            <p className="text-sm text-gray-600">{theme.description}</p>
                        </button>
                    ))}
                </div>
            </div>
        </div>
    );

    // Écran de sélection du rôle
    const RoleSelectScreen = () => {
        const [parentAnswers, setParentAnswers] = useState(null);
        const [childAnswers, setChildAnswers] = useState(null);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            const loadAnswers = async () => {
                const parent = await getAnswers(selectedTheme.id, 'parent');
                const child = await getAnswers(selectedTheme.id, 'child');
                setParentAnswers(parent);
                setChildAnswers(child);
                setLoading(false);
            };
            loadAnswers();
        }, []);

        if(loading) {
            return (
                <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 flex items-center justify-center">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
                        <p className="text-gray-600">Chargement...</p>
                    </div>
                </div>
            );
        }

        return (
            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-purple-50 flex items-center justify-center p-4">
                <div className="max-w-4xl w-full">
                    <div className="flex justify-between items-center mb-8">
                        <button onClick={() => { setScreen('themes'); setSelectedTheme(null); }} className="flex items-center gap-2 text-gray-600 font-bold hover:text-purple-600 transition-colors"><i data-lucide="arrow-left"></i> Retour aux thèmes</button>
                    </div>

                    <div className="bg-white rounded-3xl shadow-xl p-8 mb-8 text-center">
                        <h2 className="text-3xl font-bold text-gray-800 mb-2">{selectedTheme?.title}</h2>
                        <p className="text-gray-600">{selectedTheme?.description}</p>
                    </div>

                    <h2 className="text-3xl md:text-5xl font-bold text-center text-gray-800 mb-12">Qui va jouer maintenant ?</h2>

                    <div className="grid md:grid-cols-2 gap-8">
                        <button onClick={() => { setRole('parent'); setScreen('quiz'); }} className="group relative bg-white p-8 rounded-3xl shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 border-2 border-transparent hover:border-blue-200">
                            <div className="absolute top-4 right-4 bg-blue-100 text-blue-600 px-3 py-1 rounded-full text-sm font-bold">{parentAnswers ? 'Déjà joué ✅' : 'À toi de jouer !'}</div>
                            <div className="bg-blue-100 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-blue-200 transition-colors"><i data-lucide="users" className="w-12 h-12 text-blue-600"></i></div>
                            <h3 className="text-2xl font-bold text-gray-800 text-center mb-2">Je suis le Parent</h3>
                            <p className="text-gray-500 text-center">Je réponds aux questions sur mon enfant</p>
                        </button>

                        <button onClick={() => { setRole('child'); setScreen('quiz'); }} className="group relative bg-white p-8 rounded-3xl shadow-xl hover:shadow-2xl transition-all transform hover:-translate-y-2 border-2 border-transparent hover:border-pink-200">
                            <div className="absolute top-4 right-4 bg-pink-100 text-pink-600 px-3 py-1 rounded-full text-sm font-bold">{childAnswers ? 'Déjà joué ✅' : 'À toi de jouer !'}</div>
                            <div className="bg-pink-100 w-24 h-24 rounded-full flex items-center justify-center mx-auto mb-6 group-hover:bg-pink-200 transition-colors"><i data-lucide="heart" className="w-12 h-12 text-pink-600"></i></div>
                            <h3 className="text-2xl font-bold text-gray-800 text-center mb-2">Je suis l'Enfant</h3>
                            <p className="text-gray-500 text-center">Je réponds aux questions sur mon parent</p>
                        </button>
                    </div>

                    <div className="mt-12 text-center">
                        <button onClick={() => setScreen('results')} className="bg-white text-purple-600 font-bold py-3 px-8 rounded-xl shadow border border-purple-100 hover:bg-purple-50 transition-colors inline-flex items-center gap-2"><i data-lucide="star" className="w-5 h-5"></i>Voir les résultats ensemble</button>
                    </div>
                </div>
            </div>
        );
    };

    // Écran du quiz
    const QuizScreen = () => {
        const questions = getQuestionsForTheme(selectedTheme.id)?.[role] || [];
        const [index, setIndex] = useState(0);
        const [answers, setAnswers] = useState({});
        const [currentAnswer, setCurrentAnswer] = useState('');
        const [showFeedback, setShowFeedback] = useState(false);
        const [loading, setLoading] = useState(true);

        useEffect(() => {
            const loadExisting = async () => {
                const existing = await getAnswers(selectedTheme.id, role);
                if(existing) {
                    setAnswers(existing.answers);
                }
                setLoading(false);
            };
            loadExisting();
        }, []);

        if(loading) {
            return (
                <div className={`min-h-screen bg-${role === 'parent' ? 'blue' : 'pink'}-50 flex items-center justify-center`}>
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
                        <p className="text-gray-600">Chargement...</p>
                    </div>
                </div>
            );
        }

        const question = questions[index];
        const progress = ((index + 1) / questions.length) * 100;
        const colorClass = role === 'parent' ? 'blue' : 'pink';

        const handleNext = async () => {
            const newAnswers = { ...answers, [question.id]: currentAnswer };
            setAnswers(newAnswers);
            setShowFeedback(true);
            
            setTimeout(async () => {
                setShowFeedback(false);
                setCurrentAnswer('');
                if (index < questions.length - 1) {
                    setIndex(index + 1);
                } else {
                    await saveAnswers(selectedTheme.id, role, newAnswers);
                    setScreen('role-select');
                }
            }, 1500);
        };

        return (
            <div className={`min-h-screen bg-${colorClass}-50 flex items-center justify-center p-4`}>
                <div className="max-w-2xl w-full bg-white rounded-3xl shadow-2xl overflow-hidden">
                    <div className="w-full bg-gray-200 h-2"><div className={`bg-${colorClass}-500 h-2 transition-all duration-500`} style={{ width: `${progress}%` }} /></div>

                    <div className="p-8">
                        {showFeedback ? (
                            <div className="text-center py-12">
                                <i data-lucide="sparkles" className={`w-20 h-20 mx-auto text-${colorClass}-500 mb-6 animate-spin-slow`}></i>
                                <h3 className="text-2xl font-bold text-gray-800">{question.feedback}</h3>
                            </div>
                        ) : (
                            <>
                                <div className="flex justify-between items-center mb-8">
                                    <span className={`bg-${colorClass}-100 text-${colorClass}-700 px-4 py-1 rounded-full text-sm font-bold`}>Question {index + 1} / {questions.length}</span>
                                    <button onClick={() => setScreen('role-select')} className="text-gray-400 hover:text-gray-600 transition-colors">Quitter</button>
                                </div>

                                <div className="mb-4 text-center"><span className="text-sm text-gray-500 font-semibold">Thème : {selectedTheme.title}</span></div>

                                <h2 className="text-2xl md:text-3xl font-bold text-gray-800 mb-8 leading-tight">{question.question}</h2>

                                <div className="mb-8">
                                    {question.type === 'text' && (
                                        <textarea className="w-full border-2 border-gray-200 rounded-2xl p-4 text-lg focus:border-purple-500 focus:ring-0 transition-colors min-h-[150px] resize-none" placeholder="Écris ta réponse ici..." value={currentAnswer} onChange={e => setCurrentAnswer(e.target.value)} />
                                    )}
                                    {question.type === 'scale' && (
                                        <div className="flex justify-between gap-2">
                                            {[1, 2, 3, 4, 5].map(num => (
                                                <button key={num} onClick={() => setCurrentAnswer(num)} className={`flex-1 py-4 rounded-xl text-xl font-bold transition-all border-2 ${currentAnswer === num ? `bg-${colorClass}-500 text-white border-${colorClass}-500 scale-105 shadow-md` : 'border-gray-100 hover:border-gray-300 text-gray-600'}`}>{num}</button>
                                            ))}
                                        </div>
                                    )}
                                    {question.type === 'multiple' && (
                                        <div className="space-y-3">
                                            {question.options.map(opt => (
                                                <button key={opt} onClick={() => setCurrentAnswer(opt)} className={`w-full text-left p-4 rounded-xl border-2 transition-all ${currentAnswer === opt ? `border-${colorClass}-500 bg-${colorClass}-50 text-${colorClass}-700 font-semibold` : 'border-gray-100 hover:border-gray-200'}`}>{opt}</button>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                <button onClick={handleNext} disabled={!currentAnswer} className={`w-full py-4 rounded-xl font-bold text-white text-lg transition-all shadow-lg ${currentAnswer ? `bg-${colorClass}-500 hover:bg-${colorClass}-600 hover:scale-[1.02]` : 'bg-gray-300 cursor-not-allowed'}`}>Valider ma réponse</button>
                            </>
                        )}
                    </div>
                </div>
            </div>
        );
    };

    // Écran des résultats
    const ResultsScreen = () => {
        const [results, setResults] = useState({ parent: [], child: [] });
        const [loading, setLoading] = useState(true);
        const questions = getQuestionsForTheme(selectedTheme.id);

        useEffect(() => {
            const loadResults = async () => {
                const data = await getAllResults(selectedTheme.id);
                setResults(data);
                setLoading(false);
            };
            loadResults();
        }, []);

        const hasParent = results.parent.length > 0;
        const hasChild = results.child.length > 0;

        if(loading) {
            return (
                <div className="min-h-screen bg-gray-50 flex items-center justify-center">
                    <div className="text-center">
                        <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-purple-600 mx-auto mb-4"></div>
                        <p className="text-gray-600">Chargement des résultats...</p>
                    </div>
                </div>
            );
        }

        return (
            <div className="min-h-screen bg-gray-50 p-4">
                <div className="max-w-5xl mx-auto">
                    <div className="flex flex-col md:flex-row justify-between items-center mb-8 gap-4">
                        <button onClick={() => setScreen('role-select')} className="flex items-center gap-2 text-gray-600 font-bold hover:text-purple-600 transition-colors"><i data-lucide="arrow-left"></i> Retour au menu</button>
                        <div className="text-center">
                            <h1 className="text-3xl font-bold text-gray-800">Tableau des Réponses</h1>
                            <p className="text-gray-600">{selectedTheme.title}</p>
                        </div>
                        <button onClick={async () => { if (confirm('Tout effacer pour ce thème ?')) { await deleteQuiz(selectedTheme.id); setScreen('role-select'); } }} className="flex items-center gap-2 text-red-400 hover:text-red-600 text-sm font-semibold px-4 py-2 rounded-full hover:bg-red-50 transition-colors"><i data-lucide="trash-2" className="w-4 h-4"></i> Recommencer</button>
                    </div>

                    <div className="bg-blue-50 border-l-4 border-blue-400 p-4 rounded-xl mb-8">
                        <div className="flex items-start gap-3">
                            <i data-lucide="info" className="text-blue-500 w-5 h-5 mt-1"></i>
                            <div><p className="text-blue-800 font-medium">{hasParent && hasChild ? "Lisez les réponses à voix haute et discutez-en ensemble ! ❤️" : `Il manque encore les réponses de ${hasParent ? "l'Enfant" : "Parent"}. Encouragez-le à participer ! 🚀`}</p></div>
                        </div>
                    </div>

                    <div className="grid md:grid-cols-2 gap-8">
                        {/* Côté Parent */}
                        <div className={`rounded-3xl p-6 ${hasParent ? 'bg-white shadow-xl' : 'bg-gray-100 border-2 border-dashed border-gray-300'}`}>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="bg-blue-100 p-3 rounded-full"><i data-lucide="users" className="w-8 h-8 text-blue-600"></i></div>
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-800">Côté Parent</h2>
                                    <p className="text-sm text-gray-500">{hasParent ? `${results.parent.length} réponse(s)` : "En attente..."}</p>
                                </div>
                            </div>

                            {hasParent ? (
                                <div className="space-y-6">
                                    {results.parent.map((userAnswer, userIndex) => (
                                        <div key={userIndex} className="border-b last:border-0 pb-6 last:pb-0">
                                            <div className="flex items-center gap-2 mb-4">
                                                <div className="bg-blue-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                                    {userAnswer.username}
                                                </div>
                                                {userAnswer.is_mine && (
                                                    <span className="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Vous</span>
                                                )}
                                            </div>
                                            
                                            {questions.parent.map(q => (
                                                <div key={q.id} className="mb-4">
                                                    <p className="text-sm text-gray-400 font-semibold uppercase tracking-wider mb-1">Question {q.id}</p>
                                                    <p className="text-sm font-medium text-gray-800 mb-2">{q.question}</p>
                                                    <div className="bg-blue-50 p-3 rounded-xl text-blue-800 font-medium text-sm">{userAnswer.answers[q.id]}</div>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12 text-gray-400"><p>En attente de réponses...</p></div>
                            )}
                        </div>

                        {/* Côté Enfant */}
                        <div className={`rounded-3xl p-6 ${hasChild ? 'bg-white shadow-xl' : 'bg-gray-100 border-2 border-dashed border-gray-300'}`}>
                            <div className="flex items-center gap-4 mb-6">
                                <div className="bg-pink-100 p-3 rounded-full"><i data-lucide="heart" className="w-8 h-8 text-pink-600"></i></div>
                                <div>
                                    <h2 className="text-2xl font-bold text-gray-800">Côté Enfant</h2>
                                    <p className="text-sm text-gray-500">{hasChild ? `${results.child.length} réponse(s)` : "En attente..."}</p>
                                </div>
                            </div>

                            {hasChild ? (
                                <div className="space-y-6">
                                    {results.child.map((userAnswer, userIndex) => (
                                        <div key={userIndex} className="border-b last:border-0 pb-6 last:pb-0">
                                            <div className="flex items-center gap-2 mb-4">
                                                <div className="bg-pink-500 text-white px-3 py-1 rounded-full text-sm font-bold">
                                                    {userAnswer.username}
                                                </div>
                                                {userAnswer.is_mine && (
                                                    <span className="bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-bold">Vous</span>
                                                )}
                                            </div>
                                            
                                            {questions.child.map(q => (
                                                <div key={q.id} className="mb-4">
                                                    <p className="text-sm text-gray-400 font-semibold uppercase tracking-wider mb-1">Question {q.id}</p>
                                                    <p className="text-sm font-medium text-gray-800 mb-2">{q.question}</p>
                                                    <div className="bg-pink-50 p-3 rounded-xl text-pink-800 font-medium text-sm">{userAnswer.answers[q.id]}</div>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <div className="text-center py-12 text-gray-400"><p>En attente de réponses...</p></div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    };

    // Rendu selon l'écran
    switch(screen) {
        case 'themes': return <ThemesScreen />;
        case 'role-select': return <RoleSelectScreen />;
        case 'quiz': return <QuizScreen />;
        case 'results': return <ResultsScreen />;
        default: return <ThemesScreen />;
    }
};

            // Pages placeholder pour les autres sections
            const PlaceholderPage = ({ title, icon, color }) => (
                <div className={`min-h-screen bg-gradient-to-br from-${color}-50 to-gray-50 flex items-center justify-center p-4`}>
                    <div className="text-center">
                        <div className={`bg-gradient-to-r from-${color}-400 to-${color}-600 w-24 h-24 rounded-3xl flex items-center justify-center mx-auto mb-6 animate-float`}>
                            <i data-lucide={icon} className="w-12 h-12 text-white"></i>
                        </div>
                        <h1 className="text-4xl font-bold text-gray-800 mb-4">{title}</h1>
                        <p className="text-gray-600 text-lg mb-8">Cette section sera bientôt disponible ! 🚀</p>
                        <button
                            onClick={() => {
                                setActiveTab('accueil');
                                tracking.logEvent('PLACEHOLDER_BACK_CLICKED', { from: title });
                            }}
                            data-track={`placeholder_back_${title.toLowerCase()}`}
                            className={`bg-gradient-to-r from-${color}-500 to-${color}-600 text-white font-bold py-3 px-8 rounded-xl hover:shadow-lg transition-all flex items-center gap-2 mx-auto`}
                        >
                            <i data-lucide="arrow-left"></i>
                            Retour à l'accueil
                        </button>
                    </div>
                </div>
            );

            // Rendu selon l'onglet actif
            const renderPage = () => {
                switch(activeTab) {
                    case 'accueil':
                        return <AccueilPage />;
                    case 'jeux':
                        return <JeuxPage />;
                    case 'quiz':
                        return <QuizPage />;
                    case 'activites':
                        return <ActivitesPage />;
                    case 'albums':
                        return <AlbumsPage />;
                    default:
                        return <AccueilPage />;
                }
            };

            return (
                <div className="min-h-screen">
                    <Header />
                    <div className="pb-24">
                        {renderPage()}
                    </div>
                    <BottomNav />
                </div>
            );
        };

        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<App />);
        
        if ('serviceWorker' in navigator) {
    		navigator.serviceWorker.register('/sw.js')
        		.then(() => console.log('✅ Service Worker enregistré'))
        		.catch(err => console.log('❌ SW erreur:', err));
}

    </script>
</body>

</html>