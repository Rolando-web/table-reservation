<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Table Reservation System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out forwards;
        }
    </style>
</head>
<body class="bg-gradient-to-br from-amber-50 via-orange-50 to-amber-100">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0 flex items-center">
                        <i class="fas fa-coffee text-amber-600 text-3xl mr-3"></i>
                        <span class="text-2xl font-bold text-gray-800">Coffee Table</span>
                    </div>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="login.php" class="text-gray-700 hover:text-amber-600 px-4 py-2 rounded-lg font-medium transition">
                        <i class="fas fa-sign-in-alt mr-2"></i>Login
                    </a>
                    <a href="register.php" class="bg-amber-600 hover:bg-amber-700 text-white px-6 py-2 rounded-lg font-medium transition transform hover:scale-105 shadow-lg">
                        <i class="fas fa-user-plus mr-2"></i>Sign Up
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative py-20 px-4 sm:px-6 lg:px-8 overflow-hidden">
        <div class="max-w-7xl mx-auto">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <!-- Left Content -->
                <div class="animate-fade-in">
                    <h1 class="text-5xl md:text-6xl font-extrabold text-gray-900 mb-6">
                        Reserve Your
                        <span class="text-amber-600">Perfect Coffee</span>
                        Table
                    </h1>
                    <p class="text-xl text-gray-600 mb-8">
                        Experience the perfect blend of comfort and convenience. Book your favorite spot at our cozy coffee shop in just a few clicks.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="register.php" class="inline-flex items-center justify-center bg-amber-600 hover:bg-amber-700 text-white font-bold py-4 px-8 rounded-xl text-lg transition transform hover:scale-105 shadow-xl">
                            <i class="fas fa-calendar-check mr-3"></i>Book a Table Now
                        </a>
                        <a href="#features" class="inline-flex items-center justify-center border-2 border-amber-600 text-amber-600 hover:bg-amber-50 font-bold py-4 px-8 rounded-xl text-lg transition">
                            <i class="fas fa-info-circle mr-3"></i>Learn More
                        </a>
                    </div>
                </div>

                <!-- Right Image/Card -->
                <div class="relative animate-fade-in" style="animation-delay: 0.2s;">
                    <div class="grid grid-cols-2 gap-4">
                        <img src="https://images.unsplash.com/photo-1554118811-1e0d58224f24?w=400&h=300&fit=crop" 
                             alt="Coffee Shop" class="rounded-2xl shadow-2xl transform hover:scale-105 transition">
                        <img src="https://images.unsplash.com/photo-1445116572660-236099ec97a0?w=400&h=300&fit=crop" 
                             alt="Coffee Table" class="rounded-2xl shadow-2xl transform hover:scale-105 transition mt-8">
                        <img src="https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=400&h=300&fit=crop" 
                             alt="Cozy Corner" class="rounded-2xl shadow-2xl transform hover:scale-105 transition -mt-8">
                        <img src="https://images.unsplash.com/photo-1559925393-8be0ec4767c8?w=400&h=300&fit=crop" 
                             alt="Coffee Experience" class="rounded-2xl shadow-2xl transform hover:scale-105 transition">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Why Choose Us?</h2>
                <p class="text-xl text-gray-600">Everything you need for the perfect coffee experience</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2">
                    <div class="bg-amber-600 rounded-full w-16 h-16 flex items-center justify-center mb-6">
                        <i class="fas fa-clock text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Easy Booking</h3>
                    <p class="text-gray-600">Book your table in seconds with our simple and intuitive reservation system. Choose your preferred date, time, and location.</p>
                </div>

                <!-- Feature 2 -->
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2">
                    <div class="bg-amber-600 rounded-full w-16 h-16 flex items-center justify-center mb-6">
                        <i class="fas fa-couch text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Premium Tables</h3>
                    <p class="text-gray-600">Select from our variety of comfortable seating options - window seats, cozy corners, or private rooms for your gatherings.</p>
                </div>

                <!-- Feature 3 -->
                <div class="bg-gradient-to-br from-amber-50 to-orange-50 rounded-2xl p-8 shadow-lg hover:shadow-2xl transition transform hover:-translate-y-2">
                    <div class="bg-amber-600 rounded-full w-16 h-16 flex items-center justify-center mb-6">
                        <i class="fas fa-shield-alt text-white text-2xl"></i>
                    </div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Secure & Reliable</h3>
                    <p class="text-gray-600">Your reservations are secure and confirmed instantly. Manage your bookings anytime from your personal dashboard.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Gallery Section -->
    <section class="py-20 bg-gradient-to-br from-gray-50 to-amber-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-extrabold text-gray-900 mb-4">Our Coffee Shop Tables</h2>
                <p class="text-xl text-gray-600">Browse our carefully curated seating arrangements</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="group relative overflow-hidden rounded-2xl shadow-xl hover:shadow-2xl transition">
                    <img src="https://images.unsplash.com/photo-1517248135467-4c7edcad34c4?w=400&h=400&fit=crop" 
                         alt="Window Side" class="w-full h-64 object-cover transform group-hover:scale-110 transition duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-6">
                        <div class="text-white">
                            <h3 class="text-xl font-bold mb-1">Window Side</h3>
                            <p class="text-sm text-gray-200">Perfect for solo work</p>
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-2xl shadow-xl hover:shadow-2xl transition">
                    <img src="https://images.unsplash.com/photo-1567696911980-2eed69a46042?w=400&h=400&fit=crop" 
                         alt="Private Room" class="w-full h-64 object-cover transform group-hover:scale-110 transition duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-6">
                        <div class="text-white">
                            <h3 class="text-xl font-bold mb-1">Private Room</h3>
                            <p class="text-sm text-gray-200">Ideal for meetings</p>
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-2xl shadow-xl hover:shadow-2xl transition">
                    <img src="https://images.unsplash.com/photo-1501339847302-ac426a4a7cbb?w=400&h=400&fit=crop" 
                         alt="Patio" class="w-full h-64 object-cover transform group-hover:scale-110 transition duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-6">
                        <div class="text-white">
                            <h3 class="text-xl font-bold mb-1">Outdoor Patio</h3>
                            <p class="text-sm text-gray-200">Fresh air experience</p>
                        </div>
                    </div>
                </div>

                <div class="group relative overflow-hidden rounded-2xl shadow-xl hover:shadow-2xl transition">
                    <img src="https://images.unsplash.com/photo-1466978913421-dad2ebd01d17?w=400&h=400&fit=crop" 
                         alt="Large Table" class="w-full h-64 object-cover transform group-hover:scale-110 transition duration-500">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent flex items-end p-6">
                        <div class="text-white">
                            <h3 class="text-xl font-bold mb-1">Large Table</h3>
                            <p class="text-sm text-gray-200">Great for groups</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 bg-gradient-to-r from-amber-600 to-orange-600">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl md:text-5xl font-extrabold text-white mb-6">
                Ready to Reserve Your Table?
            </h2>
            <p class="text-xl text-amber-100 mb-8">
                Join hundreds of satisfied customers who enjoy our seamless reservation experience
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="register.php" class="inline-flex items-center justify-center bg-white text-amber-600 hover:bg-gray-100 font-bold py-4 px-8 rounded-xl text-lg transition transform hover:scale-105 shadow-xl">
                    <i class="fas fa-user-plus mr-3"></i>Create Account
                </a>
                <a href="login.php" class="inline-flex items-center justify-center border-2 border-white text-white hover:bg-white/10 font-bold py-4 px-8 rounded-xl text-lg transition">
                    <i class="fas fa-sign-in-alt mr-3"></i>Sign In
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <i class="fas fa-coffee text-amber-500 text-2xl mr-3"></i>
                        <span class="text-xl font-bold">Coffee Table</span>
                    </div>
                    <p class="text-gray-400">Your perfect coffee spot awaits. Reserve, relax, and enjoy.</p>
                </div>
                <div>
                    <h4 class="text-lg font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2">
                        <li><a href="login.php" class="text-gray-400 hover:text-amber-500 transition">Login</a></li>
                        <li><a href="register.php" class="text-gray-400 hover:text-amber-500 transition">Register</a></li>
                        <li><a href="#features" class="text-gray-400 hover:text-amber-500 transition">Features</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-lg font-bold mb-4">Contact</h4>
                    <ul class="space-y-2 text-gray-400">
                        <li><i class="fas fa-envelope mr-2"></i>info@coffeetable.com</li>
                        <li><i class="fas fa-phone mr-2"></i>+1 (555) 123-4567</li>
                        <li><i class="fas fa-map-marker-alt mr-2"></i>123 Coffee Street</li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2025 Coffee Table Reservation System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // Smooth scroll for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>
</body>
</html>
