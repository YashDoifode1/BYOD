<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SecureShield | Cybersecurity Consultancy Services</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #3498db;
            --accent: #e74c3c;
            --light: #ecf0f1;
            --dark: #2c3e50;
            --success: #27ae60;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            line-height: 1.6;
            color: #333;
            background-color: #f9f9f9;
        }
        
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header Styles */
        header {
            background-color: var(--primary);
            color: white;
            padding: 1rem 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
        }
        
        .logo span {
            color: var(--secondary);
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 2rem;
        }
        
        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        
        .nav-links a:hover {
            color: var(--secondary);
        }
        
        .mobile-menu {
            display: none;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        /* Hero Section */
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://images.unsplash.com/photo-1550751827-4bd374c3f58b?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80');
            background-size: cover;
            background-position: center;
            height: 100vh;
            display: flex;
            align-items: center;
            text-align: center;
            color: white;
            padding-top: 80px;
        }
        
        .hero-content {
            max-width: 800px;
            margin: 0 auto;
        }
        
        .hero h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        
        .hero p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
        }
        
        .btn {
            display: inline-block;
            background-color: var(--secondary);
            color: white;
            padding: 0.8rem 1.8rem;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s;
        }
        
        .btn:hover {
            background-color: #2980b9;
        }
        
        .btn-accent {
            background-color: var(--accent);
            margin-left: 1rem;
        }
        
        .btn-accent:hover {
            background-color: #c0392b;
        }
        
        /* Services Section */
        .services {
            padding: 5rem 0;
            background-color: white;
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            color: var(--dark);
            margin-bottom: 1rem;
        }
        
        .section-title p {
            color: #777;
            max-width: 700px;
            margin: 0 auto;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .service-card {
            background-color: var(--light);
            padding: 2rem;
            border-radius: 8px;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .service-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .service-icon {
            font-size: 3rem;
            color: var(--secondary);
            margin-bottom: 1.5rem;
        }
        
        .service-card h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        /* About Section */
        .about {
            padding: 5rem 0;
            background-color: var(--light);
        }
        
        .about-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: center;
        }
        
        .about-image img {
            width: 100%;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .about-text h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .about-text p {
            margin-bottom: 1.5rem;
            color: #555;
        }
        
        /* Testimonials */
        .testimonials {
            padding: 5rem 0;
            background-color: white;
        }
        
        .testimonial-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }
        
        .testimonial-card {
            background-color: var(--light);
            padding: 2rem;
            border-radius: 8px;
            position: relative;
        }
        
        .testimonial-card::before {
            content: '"';
            font-size: 5rem;
            color: var(--secondary);
            opacity: 0.2;
            position: absolute;
            top: 10px;
            left: 10px;
        }
        
        .testimonial-text {
            margin-bottom: 1.5rem;
            font-style: italic;
            color: #555;
        }
        
        .testimonial-author {
            display: flex;
            align-items: center;
        }
        
        .testimonial-author img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 1rem;
            object-fit: cover;
        }
        
        .author-info h4 {
            color: var(--dark);
            margin-bottom: 0.2rem;
        }
        
        .author-info p {
            color: #777;
            font-size: 0.9rem;
        }
        
        /* CTA Section */
        .cta {
            padding: 5rem 0;
            background: linear-gradient(rgba(44, 62, 80, 0.9), rgba(44, 62, 80, 0.9)), url('https://images.unsplash.com/photo-1563986768609-322da13575f3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80');
            background-size: cover;
            background-position: center;
            color: white;
            text-align: center;
        }
        
        .cta h2 {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
        
        .cta p {
            max-width: 700px;
            margin: 0 auto 2rem;
        }
        
        /* Contact Section */
        .contact {
            padding: 5rem 0;
            background-color: var(--light);
        }
        
        .contact-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
        }
        
        .contact-info h3 {
            font-size: 1.8rem;
            margin-bottom: 1.5rem;
            color: var(--dark);
        }
        
        .contact-details {
            margin-bottom: 2rem;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background-color: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }
        
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        
        .contact-form textarea {
            height: 150px;
        }
        
        /* Footer */
        footer {
            background-color: var(--dark);
            color: white;
            padding: 3rem 0 1rem;
        }
        
        .footer-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .footer-column h3 {
            font-size: 1.3rem;
            margin-bottom: 1.5rem;
            position: relative;
            padding-bottom: 0.5rem;
        }
        
        .footer-column h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 2px;
            background-color: var(--secondary);
        }
        
        .footer-column p {
            margin-bottom: 1rem;
            color: #bbb;
        }
        
        .footer-links {
            list-style: none;
        }
        
        .footer-links li {
            margin-bottom: 0.8rem;
        }
        
        .footer-links a {
            color: #bbb;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-links a:hover {
            color: var(--secondary);
        }
        
        .social-links {
            display: flex;
            gap: 1rem;
        }
        
        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            color: white;
            transition: background-color 0.3s;
        }
        
        .social-links a:hover {
            background-color: var(--secondary);
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: #bbb;
            font-size: 0.9rem;
        }
        
        /* Responsive Styles */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
            }
            
            .mobile-menu {
                display: block;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .about-content {
                grid-template-columns: 1fr;
            }
            
            .contact-container {
                grid-template-columns: 1fr;
            }
            
            .btn {
                display: block;
                width: 100%;
                margin-bottom: 1rem;
            }
            
            .btn-accent {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header>
        <div class="container">
            <nav>
    <a href="#" class="logo">Secure<span>Shield</span></a>
    <div class="mobile-menu">
        <i class="fas fa-bars"></i>
    </div>
    <ul class="nav-links">
        <li><a href="#home">Home</a></li>
        <li><a href="#services">Services</a></li>
        <li><a href="#about">About</a></li>
        <li><a href="#testimonials">Testimonials</a></li>
        <li><a href="#contact">Contact</a></li>

        <?php
        // session_start();
        if (isset($_SESSION['username'])) {
            $username = htmlspecialchars($_SESSION['username']);
            echo "<li><a href='dashboard.php'>Welcome, $username</a></li>";
        } else {
            echo "<li><a style='color: #c0392b;' href='login.php'>LOGIN HERE</a></li>";
        }
        ?>
    </ul>
</nav>

        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="container">
            <div class="hero-content">
                <h1>Protecting Your Digital Future</h1>
                <p>SecureShield provides comprehensive cybersecurity solutions to safeguard your business from evolving threats in the digital landscape.</p>
                <div class="hero-buttons">
                    <a href="#contact" class="btn">Get Started</a>
                    <a href="#services" class="btn btn-accent">Our Services</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-title">
                <h2>Our Cybersecurity Services</h2>
                <p>We offer a full range of cybersecurity services to protect your organization from threats and ensure compliance with industry standards.</p>
            </div>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3>Vulnerability Assessment</h3>
                    <p>Identify and prioritize vulnerabilities in your systems before attackers can exploit them.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                    <h3>Penetration Testing</h3>
                    <p>Simulate real-world attacks to test your defenses and uncover security weaknesses.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-user-secret"></i>
                    </div>
                    <h3>Security Consulting</h3>
                    <p>Expert guidance to develop and implement effective security strategies for your business.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-cloud"></i>
                    </div>
                    <h3>Cloud Security</h3>
                    <p>Secure your cloud infrastructure and applications with our specialized cloud security services.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-file-contract"></i>
                    </div>
                    <h3>Compliance</h3>
                    <p>Ensure compliance with GDPR, HIPAA, PCI-DSS and other regulatory requirements.</p>
                </div>
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-graduation-cap"></i>
                    </div>
                    <h3>Security Training</h3>
                    <p>Educate your team on security best practices to reduce human error risks.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-content">
                <div class="about-image">
                    <img src="https://images.unsplash.com/photo-1563986768609-322da13575f3?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1470&q=80" alt="Cybersecurity Team">
                </div>
                <div class="about-text">
                    <h2>About SecureShield</h2>
                    <p>Founded in 2015, SecureShield has grown to become a trusted partner for organizations seeking to protect their digital assets. Our team of certified security professionals brings decades of combined experience in cybersecurity.</p>
                    <p>We take a proactive approach to security, helping clients not just respond to threats but prevent them before they occur. Our methodology combines cutting-edge technology with deep expertise to deliver comprehensive protection.</p>
                    <p>At SecureShield, we believe security shouldn't be an afterthought. It should be integrated into every aspect of your business operations.</p>
                    <a href="#contact" class="btn">Learn More</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-title">
                <h2>What Our Clients Say</h2>
                <p>Hear from organizations that have partnered with SecureShield to enhance their cybersecurity posture.</p>
            </div>
            <div class="testimonial-grid">
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        <p>SecureShield's penetration testing identified critical vulnerabilities in our e-commerce platform that we were completely unaware of. Their detailed report and remediation guidance were invaluable.</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/men/32.jpg" alt="Client">
                        <div class="author-info">
                            <h4>Michael Johnson</h4>
                            <p>CTO, ShopOnline</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        <p>As a healthcare provider, compliance is critical. SecureShield helped us navigate complex HIPAA requirements and implement security controls that passed our audit with flying colors.</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/women/44.jpg" alt="Client">
                        <div class="author-info">
                            <h4>Sarah Williams</h4>
                            <p>Compliance Officer, MedCare</p>
                        </div>
                    </div>
                </div>
                <div class="testimonial-card">
                    <div class="testimonial-text">
                        <p>The security awareness training provided by SecureShield transformed our employees from security risks to our first line of defense. Phishing attempts dropped by 80% after their training.</p>
                    </div>
                    <div class="testimonial-author">
                        <img src="https://randomuser.me/api/portraits/men/75.jpg" alt="Client">
                        <div class="author-info">
                            <h4>David Thompson</h4>
                            <p>IT Director, FinanceCo</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <h2>Ready to Secure Your Business?</h2>
            <p>Don't wait until it's too late. Contact us today for a free initial consultation and take the first step toward comprehensive cybersecurity protection.</p>
            <a href="#contact" class="btn">Get Your Free Consultation</a>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-title">
                <h2>Contact Us</h2>
                <p>Get in touch with our cybersecurity experts to discuss your security needs and how we can help.</p>
            </div>
            <div class="contact-container">
                <div class="contact-info">
                    <h3>Let's Connect</h3>
                    <div class="contact-details">
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h4>Location</h4>
                                <p>123 Security Ave, Cyber City, CS 10101</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div>
                                <h4>Phone</h4>
                                <p>+1 (555) 123-4567</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div>
                                <h4>Email</h4>
                                <p>info@secureshield.com</p>
                            </div>
                        </div>
                    </div>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="contact-form">
                    <form>
                        <input type="text" placeholder="Your Name" required>
                        <input type="email" placeholder="Your Email" required>
                        <input type="text" placeholder="Subject">
                        <textarea placeholder="Your Message" required></textarea>
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3>SecureShield</h3>
                    <p>Providing comprehensive cybersecurity solutions to protect your business from evolving digital threats.</p>
                    <div class="social-links">
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                    </div>
                </div>
                <div class="footer-column">
                    <h3>Services</h3>
                    <ul class="footer-links">
                        <li><a href="#">Vulnerability Assessment</a></li>
                        <li><a href="#">Penetration Testing</a></li>
                        <li><a href="#">Security Consulting</a></li>
                        <li><a href="#">Cloud Security</a></li>
                        <li><a href="#">Compliance</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#about">About</a></li>
                        <li><a href="#testimonials">Testimonials</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>
                <div class="footer-column">
                    <h3>Newsletter</h3>
                    <p>Subscribe to our newsletter for the latest cybersecurity insights and updates.</p>
                    <form class="newsletter-form">
                        <input type="email" placeholder="Your Email">
                        <button type="submit" class="btn">Subscribe</button>
                    </form>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; 2023 SecureShield. All Rights Reserved. | <a href="#">Privacy Policy</a> | <a href="#">Terms of Service</a></p>
            </div>
        </div>
    </footer>

    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-menu').addEventListener('click', function() {
            document.querySelector('.nav-links').classList.toggle('active');
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Sticky header
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            header.classList.toggle('sticky', window.scrollY > 0);
        });
    </script>
</body>
</html>