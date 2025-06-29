<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureShield | Cybersecurity Consultancy Services</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        dark: '#0f172a',
                        accent: '#ef4444',
                    },
                    fontFamily: {
                        inter: ['Inter', 'sans-serif'],
                        space: ['Space Grotesk', 'sans-serif'],
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style type="text/tailwindcss">
        @layer utilities {
            .text-gradient {
                @apply bg-clip-text text-transparent bg-gradient-to-r from-primary-500 to-accent;
            }
            .section-title {
                @apply max-w-3xl mx-auto text-center mb-12 px-4;
            }
            .section-title h2 {
                @apply text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-4 font-space;
            }
            .section-title p {
                @apply text-lg text-gray-600 dark:text-gray-300;
            }
            .card-hover {
                @apply transition-all duration-300 hover:-translate-y-2 hover:shadow-xl;
            }
        }
    </style>
</head>
<body class="font-inter bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
    <!-- Header -->
    <header class="fixed w-full z-50 bg-white/80 dark:bg-gray-900/80 backdrop-blur-md shadow-sm">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <nav class="flex items-center justify-between h-16">
                <!-- Logo -->
                <a href="#" class="flex items-center">
                    <div class="w-8 h-8 rounded-md bg-primary-600 flex items-center justify-center text-white">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <span class="ml-2 text-xl font-bold text-gray-900 dark:text-white font-space">Secure<span class="text-primary-600">Shield</span></span>
                </a>

                <!-- Desktop Navigation -->
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#home" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition">Home</a>
                    <a href="#services" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition">Services</a>
                    <a href="#about" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition">About</a>
                    <a href="#testimonials" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition">Testimonials</a>
                    <a href="#contact" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 dark:hover:text-primary-400 font-medium transition">Contact</a>
                    
                    <?php if (isset($_SESSION['username'])): ?>
                        <a href="dashboard.php" class="text-primary-600 dark:text-primary-400 font-medium transition">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></a>
                    <?php else: ?>
                        <a href="login.php" class="bg-accent hover:bg-accent/90 text-white px-4 py-2 rounded-md font-medium transition">Login</a>
                    <?php endif; ?>

                    <!-- Dark mode toggle -->
                    <button id="theme-toggle" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg p-2">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:block"></i>
                    </button>
                </div>

                <!-- Mobile menu button -->
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-button" class="text-gray-700 dark:text-gray-300 hover:text-primary-600 p-2">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </nav>
        </div>

        <!-- Mobile menu -->
        <div id="mobile-menu" class="hidden md:hidden bg-white dark:bg-gray-800 shadow-lg rounded-b-lg">
            <div class="px-4 py-3 space-y-2">
                <a href="#home" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Home</a>
                <a href="#services" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Services</a>
                <a href="#about" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">About</a>
                <a href="#testimonials" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Testimonials</a>
                <a href="#contact" class="block px-3 py-2 rounded-md text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Contact</a>
                
                <?php if (isset($_SESSION['username'])): ?>
                    <a href="dashboard.php" class="block px-3 py-2 rounded-md text-primary-600 dark:text-primary-400 hover:bg-gray-100 dark:hover:bg-gray-700 transition">Welcome, <?= htmlspecialchars($_SESSION['username']) ?></a>
                <?php else: ?>
                    <a href="login.php" class="block px-3 py-2 rounded-md bg-accent text-white text-center hover:bg-accent/90 transition">Login</a>
                <?php endif; ?>
                
                <div class="flex justify-center pt-2">
                    <button id="theme-toggle-mobile" class="text-gray-500 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg p-2">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:block"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="home" class="pt-24 pb-12 md:pt-32 md:pb-20 bg-gradient-to-br from-gray-900 to-gray-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="max-w-4xl mx-auto text-center">
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold mb-6 font-space">
                    <span class="block">Protecting Your</span>
                    <span class="text-gradient">Digital Future</span>
                </h1>
                <p class="text-xl md:text-2xl text-gray-300 mb-8 max-w-3xl mx-auto">
                    Comprehensive cybersecurity solutions to safeguard your business from evolving threats in the digital landscape.
                </p>
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="#contact" class="px-8 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-md transition duration-300 transform hover:scale-105">
                        Get Started
                    </a>
                    <a href="#services" class="px-8 py-3 bg-gray-800 hover:bg-gray-700 text-white font-medium rounded-md transition duration-300 transform hover:scale-105">
                        Our Services
                    </a>
                </div>
            </div>
            <div class="mt-16 grid grid-cols-2 md:grid-cols-4 gap-4 max-w-4xl mx-auto">
                <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-gray-700 flex items-center justify-center">
                    <i class="fas fa-shield-alt text-3xl text-primary-400"></i>
                </div>
                <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-gray-700 flex items-center justify-center">
                    <i class="fas fa-lock text-3xl text-primary-400"></i>
                </div>
                <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-gray-700 flex items-center justify-center">
                    <i class="fas fa-cloud text-3xl text-primary-400"></i>
                </div>
                <div class="bg-white/10 p-4 rounded-lg backdrop-blur-sm border border-gray-700 flex items-center justify-center">
                    <i class="fas fa-user-shield text-3xl text-primary-400"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="py-16 md:py-24 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-title">
                <h2>Our Cybersecurity Services</h2>
                <p>We offer a full range of cybersecurity services to protect your organization from threats and ensure compliance with industry standards.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Service Card 1 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-shield-alt text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Vulnerability Assessment</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Identify and prioritize vulnerabilities in your systems before attackers can exploit them.
                    </p>
                </div>

                <!-- Service Card 2 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-lock text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Penetration Testing</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Simulate real-world attacks to test your defenses and uncover security weaknesses.
                    </p>
                </div>

                <!-- Service Card 3 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-user-secret text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Security Consulting</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Expert guidance to develop and implement effective security strategies for your business.
                    </p>
                </div>

                <!-- Service Card 4 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-cloud text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Cloud Security</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Secure your cloud infrastructure and applications with our specialized cloud security services.
                    </p>
                </div>

                <!-- Service Card 5 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-file-contract text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Compliance</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Ensure compliance with GDPR, HIPAA, PCI-DSS and other regulatory requirements.
                    </p>
                </div>

                <!-- Service Card 6 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="w-14 h-14 bg-primary-100 dark:bg-primary-900/30 rounded-lg flex items-center justify-center mb-4">
                        <i class="fas fa-graduation-cap text-2xl text-primary-600 dark:text-primary-400"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-3">Security Training</h3>
                    <p class="text-gray-600 dark:text-gray-400">
                        Educate your team on security best practices to reduce human error risks.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="py-16 md:py-24 bg-gray-50 dark:bg-gray-800">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div class="order-2 lg:order-1">
                    <h2 class="text-3xl md:text-4xl font-bold text-gray-900 dark:text-white mb-6 font-space">
                        About <span class="text-primary-600">SecureShield</span>
                    </h2>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        Founded in 2015, SecureShield has grown to become a trusted partner for organizations seeking to protect their digital assets. Our team of certified security professionals brings decades of combined experience in cybersecurity.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 mb-4">
                        We take a proactive approach to security, helping clients not just respond to threats but prevent them before they occur. Our methodology combines cutting-edge technology with deep expertise to deliver comprehensive protection.
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 mb-6">
                        At SecureShield, we believe security shouldn't be an afterthought. It should be integrated into every aspect of your business operations.
                    </p>
                    <a href="#contact" class="inline-block px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-md transition duration-300">
                        Learn More
                    </a>
                </div>
                <div class="order-1 lg:order-2 relative">
                    <div class="bg-primary-500/10 rounded-xl overflow-hidden aspect-video">
                        <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" 
                             alt="Cybersecurity Team" 
                             class="w-full h-full object-cover rounded-xl shadow-lg">
                    </div>
                    <div class="absolute -bottom-6 -right-6 w-24 h-24 bg-accent rounded-lg flex items-center justify-center shadow-lg">
                        <i class="fas fa-lock-open text-white text-3xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-12 bg-primary-600 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                <div class="p-4">
                    <div class="text-4xl font-bold mb-2 font-space">250+</div>
                    <div class="text-primary-100">Clients Protected</div>
                </div>
                <div class="p-4">
                    <div class="text-4xl font-bold mb-2 font-space">99.9%</div>
                    <div class="text-primary-100">Success Rate</div>
                </div>
                <div class="p-4">
                    <div class="text-4xl font-bold mb-2 font-space">50+</div>
                    <div class="text-primary-100">Security Experts</div>
                </div>
                <div class="p-4">
                    <div class="text-4xl font-bold mb-2 font-space">24/7</div>
                    <div class="text-primary-100">Monitoring</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section id="testimonials" class="py-16 md:py-24 bg-white dark:bg-gray-900">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-title">
                <h2>What Our Clients Say</h2>
                <p>Hear from organizations that have partnered with SecureShield to enhance their cybersecurity posture.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                <!-- Testimonial 1 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="text-primary-400 text-5xl opacity-20 mb-4">"</div>
                    <p class="text-gray-600 dark:text-gray-300 italic mb-6">
                        SecureShield's penetration testing identified critical vulnerabilities in our e-commerce platform that we were completely unaware of. Their detailed report and remediation guidance were invaluable.
                    </p>
                    <div class="flex items-center">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Michael Johnson" class="w-12 h-12 rounded-full object-cover mr-4">
                        <div>
                            <h4 class="font-bold text-gray-900 dark:text-white">Michael Johnson</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">CTO, ShopOnline</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 2 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="text-primary-400 text-5xl opacity-20 mb-4">"</div>
                    <p class="text-gray-600 dark:text-gray-300 italic mb-6">
                        As a healthcare provider, compliance is critical. SecureShield helped us navigate complex HIPAA requirements and implement security controls that passed our audit with flying colors.
                    </p>
                    <div class="flex items-center">
                        <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Sarah Williams" class="w-12 h-12 rounded-full object-cover mr-4">
                        <div>
                            <h4 class="font-bold text-gray-900 dark:text-white">Sarah Williams</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">Compliance Officer, MedCare</p>
                        </div>
                    </div>
                </div>

                <!-- Testimonial 3 -->
                <div class="bg-gray-50 dark:bg-gray-800 p-6 rounded-xl shadow-md card-hover">
                    <div class="text-primary-400 text-5xl opacity-20 mb-4">"</div>
                    <p class="text-gray-600 dark:text-gray-300 italic mb-6">
                        The security awareness training provided by SecureShield transformed our employees from security risks to our first line of defense. Phishing attempts dropped by 80% after their training.
                    </p>
                    <div class="flex items-center">
                        <img src="https://randomuser.me/api/portraits/men/75.jpg" alt="David Thompson" class="w-12 h-12 rounded-full object-cover mr-4">
                        <div>
                            <h4 class="font-bold text-gray-900 dark:text-white">David Thompson</h4>
                            <p class="text-gray-500 dark:text-gray-400 text-sm">IT Director, FinanceCo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-16 md:py-24 bg-gradient-to-r from-primary-600 to-primary-800 text-white">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl md:text-4xl font-bold mb-6 font-space">Ready to Secure Your Business?</h2>
            <p class="text-xl text-primary-100 max-w-3xl mx-auto mb-8">
                Don't wait until it's too late. Contact us today for a free initial consultation and take the first step toward comprehensive cybersecurity protection.
            </p>
            <a href="#contact" class="inline-block px-8 py-4 bg-white text-primary-600 hover:bg-gray-100 font-bold rounded-md transition duration-300 transform hover:scale-105">
                Get Your Free Consultation
            </a>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="py-16 md:py-24 bg-gray-50 dark:bg-gray-900">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with our cybersecurity experts to discuss your security needs and how we can help.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
                <div>
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 font-space">Let's Connect</h3>
                    
                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-primary-100 dark:bg-primary-900/30 p-3 rounded-lg text-primary-600 dark:text-primary-400">
                                <i class="fas fa-map-marker-alt text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">Location</h4>
                                <p class="text-gray-600 dark:text-gray-400">123 Security Ave, Cyber City, CS 10101</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-primary-100 dark:bg-primary-900/30 p-3 rounded-lg text-primary-600 dark:text-primary-400">
                                <i class="fas fa-phone-alt text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">Phone</h4>
                                <p class="text-gray-600 dark:text-gray-400">+1 (555) 123-4567</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <div class="flex-shrink-0 bg-primary-100 dark:bg-primary-900/30 p-3 rounded-lg text-primary-600 dark:text-primary-400">
                                <i class="fas fa-envelope text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white">Email</h4>
                                <p class="text-gray-600 dark:text-gray-400">info@secureshield.com</p>
                            </div>
                        </div>
                    </div>

                    <div class="mt-8">
                        <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Follow Us</h4>
                        <div class="flex space-x-4">
                            <a href="#" class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-primary-600 hover:text-white transition">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-primary-600 hover:text-white transition">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                            <a href="#" class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-primary-600 hover:text-white transition">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="w-10 h-10 bg-gray-200 dark:bg-gray-700 rounded-full flex items-center justify-center text-gray-700 dark:text-gray-300 hover:bg-primary-600 hover:text-white transition">
                                <i class="fab fa-instagram"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div>
                    <form class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Name</label>
                            <input type="text" id="name" class="w-full px-4 py-3 rounded-md border border-gray-300 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:text-white transition">
                        </div>
                        
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Email</label>
                            <input type="email" id="email" class="w-full px-4 py-3 rounded-md border border-gray-300 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:text-white transition">
                        </div>
                        
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Subject</label>
                            <input type="text" id="subject" class="w-full px-4 py-3 rounded-md border border-gray-300 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:text-white transition">
                        </div>
                        
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Your Message</label>
                            <textarea id="message" rows="5" class="w-full px-4 py-3 rounded-md border border-gray-300 dark:border-gray-700 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 dark:bg-gray-800 dark:text-white transition"></textarea>
                        </div>
                        
                        <button type="submit" class="w-full px-6 py-3 bg-primary-600 hover:bg-primary-700 text-white font-medium rounded-md transition duration-300">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 pt-16 pb-8">
        <div class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-12">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="w-8 h-8 rounded-md bg-primary-600 flex items-center justify-center text-white">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <span class="ml-2 text-xl font-bold text-white font-space">SecureShield</span>
                    </div>
                    <p class="mb-4">
                        Providing comprehensive cybersecurity solutions to protect your business from evolving digital threats.
                    </p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-400 hover:bg-primary-600 hover:text-white transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-400 hover:bg-primary-600 hover:text-white transition">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="#" class="w-10 h-10 bg-gray-800 rounded-full flex items-center justify-center text-gray-400 hover:bg-primary-600 hover:text-white transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-white mb-4 relative pb-2 after:absolute after:left-0 after:bottom-0 after:w-10 after:h-0.5 after:bg-primary-600">
                        Services
                    </h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="hover:text-primary-400 transition">Vulnerability Assessment</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition">Penetration Testing</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition">Security Consulting</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition">Cloud Security</a></li>
                        <li><a href="#" class="hover:text-primary-400 transition">Compliance</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-white mb-4 relative pb-2 after:absolute after:left-0 after:bottom-0 after:w-10 after:h-0.5 after:bg-primary-600">
                        Quick Links
                    </h3>
                    <ul class="space-y-2">
                        <li><a href="#home" class="hover:text-primary-400 transition">Home</a></li>
                        <li><a href="#services" class="hover:text-primary-400 transition">Services</a></li>
                        <li><a href="#about" class="hover:text-primary-400 transition">About</a></li>
                        <li><a href="#testimonials" class="hover:text-primary-400 transition">Testimonials</a></li>
                        <li><a href="#contact" class="hover:text-primary-400 transition">Contact</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="text-lg font-bold text-white mb-4 relative pb-2 after:absolute after:left-0 after:bottom-0 after:w-10 after:h-0.5 after:bg-primary-600">
                        Newsletter
                    </h3>
                    <p class="mb-4">
                        Subscribe to our newsletter for the latest cybersecurity insights and updates.
                    </p>
                    <form class="flex">
                        <input type="email" placeholder="Your Email" class="px-4 py-2 w-full rounded-l-md focus:outline-none focus:ring-2 focus:ring-primary-600 bg-gray-800 text-white">
                        <button type="submit" class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-2 rounded-r-md transition">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="pt-8 border-t border-gray-800 text-center text-sm">
                <p>&copy; 2023 SecureShield. All Rights Reserved. | <a href="#" class="hover:text-primary-400 transition">Privacy Policy</a> | <a href="#" class="hover:text-primary-400 transition">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        });

        // Dark mode toggle
        const themeToggle = document.getElementById('theme-toggle');
        const themeToggleMobile = document.getElementById('theme-toggle-mobile');
        
        function toggleTheme() {
            if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('color-theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('color-theme', 'dark');
            }
        }
        
        themeToggle.addEventListener('click', toggleTheme);
        themeToggleMobile.addEventListener('click', toggleTheme);

        // Check for saved theme preference
        if(localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Close mobile menu if open
                    const mobileMenu = document.getElementById('mobile-menu');
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                    }
                }
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 100) {
                header.classList.add('bg-white/90', 'dark:bg-gray-900/90');
                header.classList.remove('bg-white/80', 'dark:bg-gray-900/80');
            } else {
                header.classList.add('bg-white/80', 'dark:bg-gray-900/80');
                header.classList.remove('bg-white/90', 'dark:bg-gray-900/90');
            }
        });

        // Contact form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Get form data
            const name = document.getElementById('name').value;
            const email = document.getElementById('email').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            // Basic validation
            if (!name || !email || !subject || !message) {
                alert('Please fill in all fields.');
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // Simulate form submission
            alert('Thank you for your message! We will get back to you soon.');
            
            // Reset form
            this.reset();
        });

        // Newsletter form submission
        document.querySelector('footer form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = this.querySelector('input[type="email"]').value;
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                alert('Please enter a valid email address.');
                return;
            }
            
            // Simulate newsletter subscription
            alert('Thank you for subscribing to our newsletter!');
            
            // Reset form
            this.reset();
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fadeInUp');
                }
            });
        }, observerOptions);

        // Observe all service cards and testimonials
        document.querySelectorAll('.card-hover').forEach(card => {
            observer.observe(card);
        });
    </script>
</body>
</html>