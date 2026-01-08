<?php
/**
 * Dog House Market - Homepage
 * Professional and sophisticated design with responsive layout
 */

// Include database connection
$conn = require_once 'dbconnect.php';

// Start session to check user login status
session_start();

// Check if user is logged in
$loggedIn = isset($_SESSION['user_id']);

// Fetch company information from database with the updated schema
$companyInfo = [];
$companyQuery = "SELECT id, company_name, address, city, postal_code, phone, color, 
                        logo, favicon, banner_image, email_general, email_support, 
                        working_hours, created_at, font 
                 FROM company_info LIMIT 1";
$result = mysqli_query($conn, $companyQuery);

if ($result && mysqli_num_rows($result) > 0) {
    $companyInfo = mysqli_fetch_assoc($result);
    // Map color field to primary_color for compatibility with existing code
    $companyInfo['primary_color'] = $companyInfo['color'] ?? '#FFA500';
    
    // Set working_hours to hours for compatibility with existing template
    $companyInfo['hours'] = $companyInfo['working_hours'];
    
    // Set general email to email for compatibility
    $companyInfo['email'] = $companyInfo['email_general'];
    
    // Handle description as it's not in the database schema
    if (!isset($companyInfo['description'])) {
        $companyInfo['description'] = 'We provide premium dog houses for all breeds and sizes. Our products are made with high-quality materials and built to last.';
    }
    
    // Format the business hours for display
    if (isset($companyInfo['working_hours'])) {
        $companyInfo['business_hours'] = nl2br(htmlspecialchars($companyInfo['working_hours']));
    }
} else {
    // Default company info if not found in database
    $companyInfo = [
        'company_name' => 'Doghouse Market',
        'tagline' => 'Quality Dog Houses for Your Furry Friends',
        'description' => 'We provide premium dog houses for all breeds and sizes. Our products are made with high-quality materials and built to last.',
        'address' => 'Oklahoma City, OK 73149',
        'city' => 'Oklahoma City',
        'postal_code' => '73149',
        'primary_color' => '#FFA500',
        'email' => 'atimalothbrok@gmail.com',
        'email_general' => 'atimalothbrok@gmail.com',
        'email_support' => 'support@doghousemarket.com',
        'business_hours' => 'Monday 8:00am-4:30pm<br>Tuesday 8:00am-4:30pm<br>Wednesday 8:00am-4:30pm',
        'font' => 'Arial'
    ];
}

// Check if dogs table exists and fetch featured dogs
$featuredDogs = [];
$dogsTableExists = false;
$dogsExistsQuery = "SHOW TABLES LIKE 'dogs'";
$dogsExists = mysqli_query($conn, $dogsExistsQuery);

if ($dogsExists && mysqli_num_rows($dogsExists) > 0) {
    $dogsTableExists = true;
    // Fetch 6 dogs from the database, ordered by newest first
    $dogsQuery = "SELECT * FROM dogs ORDER BY created_at DESC LIMIT 6";
    $dogsResult = mysqli_query($conn, $dogsQuery);

    if ($dogsResult && mysqli_num_rows($dogsResult) > 0) {
        while ($dog = mysqli_fetch_assoc($dogsResult)) {
            $featuredDogs[] = $dog;
        }
    }
}

// Check if products table exists before querying it
$featuredProducts = [];
$tableExistsQuery = "SHOW TABLES LIKE 'products'";
$tableExists = mysqli_query($conn, $tableExistsQuery);

if ($tableExists && mysqli_num_rows($tableExists) > 0) {
    // Only query the products table if it exists
    $productQuery = "SELECT * FROM products WHERE featured = 1 LIMIT 4";
    $productResult = mysqli_query($conn, $productQuery);

    if ($productResult && mysqli_num_rows($productResult) > 0) {
        while ($product = mysqli_fetch_assoc($productResult)) {
            $featuredProducts[] = $product;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: <?php echo htmlspecialchars($companyInfo['primary_color'] ?? '#FFA500'); ?>;
            --secondary-color: #2c3e50;
            --accent-color: #e67e22;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #212529;
            --text-light: #6c757d;
            --white: #ffffff;
            --section-padding: 6rem 0;
            --border-radius: 8px;
            --box-shadow: 0 5px 30px rgba(0,0,0,0.08);
            --main-font: <?php echo htmlspecialchars($companyInfo['font'] ?? 'Arial'); ?>, sans-serif;
        }
        
        body {
            font-family: var(--main-font);
            line-height: 1.7;
            color: var(--text-color);
            overflow-x: hidden;
            scroll-behavior: smooth;
            background-color: var(--white);
        }
        
        h1, h2, h3, h4, h5 {
            font-family: var(--main-font);
            font-weight: 700;
            line-height: 1.3;
        }
        
        .section-title {
            position: relative;
            margin-bottom: 3rem;
            text-align: center;
        }
        
        .section-title h2 {
            font-size: 2.8rem;
            position: relative;
            display: inline-block;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            left: 50%;
            bottom: -15px;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
            transform: translateX(-50%);
        }
        
        .section-title p {
            color: var(--text-light);
            font-size: 1.1rem;
            max-width: 650px;
            margin: 20px auto 0;
        }

        /* Header & Navigation */
        .navbar {
            padding: 15px 0;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.95);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            font-family: var(--main-font);
        }
        
        .navbar-brand {
            font-family: var(--main-font);
            font-weight: 700;
            font-size: 1.8rem;
            color: var(--primary-color) !important;
            display: flex;
            align-items: center;
        }
        
        .navbar-logo {
            height: 40px;
            width: auto;
            object-fit: contain;
            margin-right: 10px;
        }
        
        .nav-link {
            font-weight: 500;
            margin: 0 10px;
            padding: 8px 0 !important;
            position: relative;
            color: var(--dark-color) !important;
            transition: all 0.3s ease;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 2px;
            background-color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .nav-link:hover::after, .nav-link.active::after {
            width: 100%;
        }
        
        .navbar-toggler {
            border: none;
            padding: 0;
        }
        
        .navbar-toggler:focus {
            box-shadow: none;
        }
        
        .auth-buttons .btn {
            margin-left: 10px;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .auth-buttons .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .auth-buttons .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .auth-buttons .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .auth-buttons .btn-primary:hover {
            background-color: darken(var(--primary-color), 10%);
            border-color: darken(var(--primary-color), 10%);
        }

        /* Hero Section */
        .hero-carousel {
            position: relative;
            font-family: var(--main-font);
        }
        
        .hero-carousel .carousel-item {
            height: 80vh;
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        .carousel-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(0,0,0,0.7) 30%, rgba(0,0,0,0.4));
            display: flex;
            align-items: center;
        }
        
        .carousel-content {
            color: white;
            max-width: 650px;
            padding: 0 20px;
            margin-left: 8%;
            text-align: left;
        }
        
        .carousel-content h1 {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 20px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1s forwards;
        }
        
        .carousel-content p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1s 0.3s forwards;
        }
        
        .carousel-content .btn {
            padding: 12px 30px;
            font-weight: 600;
            text-transform: uppercase;
            border-radius: 30px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 1s 0.6s forwards;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Dog Cards Section */
        .dogs-section {
            padding: var(--section-padding);
            background-color: var(--white);
        }
        
        .dog-card {
            position: relative;
            border: none;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            height: 100%;
        }
        
        .dog-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .dog-card .card-img-wrapper {
            position: relative;
            overflow: hidden;
            height: 240px;
        }
        
        .dog-card img {
            transition: transform 0.5s ease;
            height: 100%;
            width: 100%;
            object-fit: cover;
        }
        
        .dog-card:hover img {
            transform: scale(1.1);
        }
        
        .dog-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.6) 0%, rgba(0,0,0,0) 50%);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .dog-card:hover .overlay {
            opacity: 1;
        }
        
        .dog-card .card-body {
            padding: 25px;
        }
        
        .dog-card .card-title {
            font-size: 1.4rem;
            margin-bottom: 10px;
        }
        
        .dog-card .dog-info {
            margin-bottom: 20px;
        }
        
        .dog-card .dog-info span {
            display: block;
            margin-bottom: 5px;
            color: var(--text-light);
            font-size: 0.95rem;
        }
        
        .dog-card .dog-info span i {
            color: var(--primary-color);
            margin-right: 8px;
            width: 18px;
            text-align: center;
        }
        
        .dog-card .price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 20px;
        }
        
        .dog-card .btn {
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
        }
        
        .view-all-btn {
            border-radius: 30px;
            padding: 12px 35px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 30px;
        }

        /* Categories Section */
        .categories-section {
            padding: var(--section-padding);
            background-color: var(--light-color);
        }
        
        .category-card {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            height: 350px;
        }
        
        .category-card:hover {
            transform: translateY(-10px);
        }
        
        .category-card img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .category-card:hover img {
            transform: scale(1.1);
        }
        
        .category-card .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to top, rgba(0,0,0,0.8) 20%, rgba(0,0,0,0.3));
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            padding: 30px;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .overlay {
            background: linear-gradient(to top, rgba(0,0,0,0.9) 20%, rgba(0,0,0,0.4));
        }
        
        .category-card .category-title {
            color: white;
            font-size: 1.8rem;
            margin-bottom: 10px;
            transform: translateY(20px);
            transition: all 0.3s ease;
        }
        
        .category-card:hover .category-title {
            transform: translateY(0);
        }
        
        .category-card .category-desc {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 20px;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .category-desc {
            transform: translateY(0);
            opacity: 1;
        }
        
        .category-card .btn {
            align-self: flex-start;
            padding: 10px 25px;
            border-radius: 30px;
            text-transform: uppercase;
            font-weight: 500;
            letter-spacing: 1px;
            transform: translateY(20px);
            opacity: 0;
            transition: all 0.3s ease 0.1s;
        }
        
        .category-card:hover .btn {
            transform: translateY(0);
            opacity: 1;
        }

        /* Why Choose Us Section */
        .why-choose {
            padding: var(--section-padding);
            background-color: var(--white);
        }
        
        .why-item {
            text-align: center;
            padding: 40px 25px;
            border-radius: var(--border-radius);
            background-color: white;
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            margin-bottom: 30px;
            height: 100%;
        }
        
        .why-item:hover {
            transform: translateY(-10px);
        }
        
        .why-item .icon-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            transition: all 0.3s ease;
        }
        
        .why-item:hover .icon-wrapper {
            background-color: var(--primary-color);
        }
        
        .why-item i {
            font-size: 2.5rem;
            color: var(--primary-color);
            transition: all 0.3s ease;
        }
        
        .why-item:hover i {
            color: white;
        }
        
        .why-item h4 {
            font-size: 1.4rem;
            margin-bottom: 15px;
        }
        
        .why-item p {
            color: var(--text-light);
            margin-bottom: 0;
        }

        /* About Section */
        .about-section {
            padding: var(--section-padding);
            background-color: var(--light-color);
            position: relative;
        }
        
        .about-image {
            position: relative;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
        }
        
        .about-image img {
            width: 100%;
            height: auto;
            border-radius: var(--border-radius);
            transition: transform 0.5s ease;
        }
        
        .about-image:hover img {
            transform: scale(1.05);
        }
        
        .about-content {
            padding: 30px;
        }
        
        .about-content h2 {
            font-size: 2.5rem;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .about-content h2::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 60px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .about-content p {
            margin-bottom: 20px;
            color: var(--text-color);
            font-size: 1.05rem;
        }
        
        .about-content .signature {
            font-family: 'Playfair Display', serif;
            font-size: 1.8rem;
            color: var(--primary-color);
            margin-top: 20px;
        }
        
        .about-content .btn {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 500;
            text-transform: uppercase;
            margin-top: 20px;
        }

        /* Contact Section */
        .contact-section {
            padding: var(--section-padding);
            background-color: var(--white);
            position: relative;
        }
        
        .contact-form {
            background-color: white;
            padding: 40px;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .contact-form .form-control {
            border-radius: 0;
            padding: 12px 15px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .contact-form .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: none;
        }
        
        .contact-form label {
            font-weight: 500;
            margin-bottom: 8px;
        }
        
        .contact-form .btn {
            padding: 12px 30px;
            border-radius: 30px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .contact-info {
            padding: 30px;
        }
        
        .contact-info h3 {
            font-size: 1.8rem;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 15px;
        }
        
        .contact-info h3::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 40px;
            height: 3px;
            background-color: var(--primary-color);
        }
        
        .contact-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 30px;
        }
        
        .contact-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: rgba(var(--primary-color-rgb), 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
        }
        
        .contact-icon i {
            font-size: 1.2rem;
            color: var(--primary-color);
        }
        
        .contact-info-content h5 {
            font-size: 1.1rem;
            margin-bottom: 5px;
        }
        
        .contact-info-content p {
            color: var(--text-light);
            margin-bottom: 0;
        }

        /* Footer */
        .footer {
            background-color: var(--dark-color);
            color: white;
            padding: 80px 0 20px;
            position: relative;
        }
        
        .footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color), var(--primary-color));
        }
        
        .footer h5 {
            font-size: 1.4rem;
            margin-bottom: 25px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .footer h5::after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 30px;
            height: 2px;
            background-color: var(--primary-color);
        }
        
        .footer-content {
            margin-bottom: 30px;
        }
        
        .footer-content p {
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 20px;
        }
        
        .social-links {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .social-links a {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }
        
        .social-links a:hover {
            background-color: var(--primary-color);
            transform: translateY(-5px);
        }
        
        .footer-links {
            list-style: none;
            padding-left: 0;
        }
        
        .footer-links li {
            margin-bottom: 15px;
        }
        
        .footer-links a {
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
        }
        
        .footer-links a i {
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .footer-links a:hover {
            color: white;
            padding-left: 5px;
        }
        
        .footer-contact li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 15px;
            color: rgba(255, 255, 255, 0.7);
        }
        
        .footer-contact i {
            margin-right: 15px;
            color: var(--primary-color);
        }
        
        .newsletter-form {
            position: relative;
            margin-top: 20px;
        }
        
        .newsletter-form .form-control {
            height: 50px;
            border-radius: 30px;
            padding-left: 20px;
            padding-right: 130px;
            border: none;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .newsletter-form .form-control:focus {
            box-shadow: 0 0 10px rgba(255, 255, 255, 0.1);
        }
        
        .newsletter-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        .newsletter-form .btn {
            position: absolute;
            right: 5px;
            top: 5px;
            border-radius: 30px;
            padding: 10px 25px;
            font-weight: 500;
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            text-transform: uppercase;
            font-size: 0.8rem;
        }
        
        .copyright {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }
        
        .back-to-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary-color);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            z-index: 99;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .back-to-top.active {
            opacity: 1;
            visibility: visible;
        }
        
        .back-to-top:hover {
            background-color: var(--accent-color);
            color: white;
            transform: translateY(-5px);
        }

        /* Responsive Adjustments */
        @media (max-width: 1199.98px) {
            .carousel-content h1 {
                font-size: 3rem;
            }
            
            .carousel-content p {
                font-size: 1.1rem;
            }
            
            .hero-carousel .carousel-item {
                height: 70vh;
            }
            
            .section-title h2 {
                font-size: 2.5rem;
            }
        }
        
        @media (max-width: 991.98px) {
            .carousel-content h1 {
                font-size: 2.5rem;
            }
            
            .carousel-content p {
                font-size: 1rem;
            }
            
            .hero-carousel .carousel-item {
                height: 60vh;
            }
            
            .section-title h2 {
                font-size: 2.2rem;
            }
            
            .about-image {
                margin-bottom: 30px;
            }
            
            .contact-info {
                margin-top: 50px;
            }
            
            .navbar-logo {
                height: 35px;
            }
        }
        
        @media (max-width: 767.98px) {
            .carousel-content {
                margin-left: 0;
                text-align: center;
                padding: 0 20px;
            }
            
            .carousel-content h1 {
                font-size: 2rem;
            }
            
            .carousel-content p {
                font-size: 0.9rem;
            }
            
            .carousel-overlay {
                background: rgba(0, 0, 0, 0.6);
                justify-content: center;
            }
            
            .hero-carousel .carousel-item {
                height: 50vh;
            }
            
            .section-title h2 {
                font-size: 2rem;
            }
            
            .footer-column {
                margin-bottom: 30px;
            }
            
            .navbar-brand {
                font-size: 1.5rem;
            }
            
            .navbar-logo {
                height: 30px;
            }
        }
        
        @media (max-width: 575.98px) {
            .carousel-content h1 {
                font-size: 1.8rem;
            }
            
            .carousel-content .btn {
                padding: 10px 20px;
                font-size: 0.8rem;
            }
            
            .contact-form {
                padding: 25px;
            }
            
            .navbar-logo {
                height: 28px;
            }
        }

        /* Animation classes for AOS library */
        [data-aos] {
            opacity: 0;
            transform: translateY(30px);
            transition: transform 0.8s ease, opacity 0.8s ease;
        }

        [data-aos].aos-animate {
            opacity: 1;
            transform: translateY(0);
        }

        /* Custom styles for primary color and accent colors */
        .text-primary {
            color: var(--primary-color) !important;
        }
        
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: darken(var(--primary-color), 10%);
            border-color: darken(var(--primary-color), 10%);
        }
        
        .btn-outline-primary {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        /* Convert primary-color HEX to RGB for opacity support */
        :root {
            --primary-color-rgb: <?php 
                $hex = ltrim($companyInfo['primary_color'] ?? '#FFA500', '#');
                if (strlen($hex) == 3) {
                    $r = hexdec(substr($hex, 0, 1).substr($hex, 0, 1));
                    $g = hexdec(substr($hex, 1, 1).substr($hex, 1, 1));
                    $b = hexdec(substr($hex, 2, 1).substr($hex, 2, 1));
                } else {
                    $r = hexdec(substr($hex, 0, 2));
                    $g = hexdec(substr($hex, 2, 2));
                    $b = hexdec(substr($hex, 4, 2));
                }
                echo "$r, $g, $b";
            ?>;
        }
    </style>

    <!-- Font Preload/Import -->
    <?php if (isset($companyInfo['font']) && $companyInfo['font'] !== 'Arial' && $companyInfo['font'] !== 'Verdana' && $companyInfo['font'] !== 'Helvetica' && $companyInfo['font'] !== 'Tahoma'): ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=<?php echo str_replace(' ', '+', $companyInfo['font']); ?>:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="<?php echo $loggedIn ? 'user/dashboard.php' : 'index.php'; ?>">
                <?php if (!empty($companyInfo['logo'])): ?>
                    <img src="<?php echo htmlspecialchars($companyInfo['logo']); ?>" alt="<?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>" class="navbar-logo me-2">
                <?php endif; ?>
                <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <i class="fas fa-bars"></i>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav mx-auto">
                    <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="dogs.php">Dogs</a></li>
                    <li class="nav-item"><a class="nav-link" href="#about">About Us</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                </ul>
                <div class="auth-buttons d-flex flex-wrap">
                    <?php if ($loggedIn): ?>
                        <a href="user/dashboard.php" class="btn btn-outline-primary">Dashboard</a>
                        <a href="logout.php" class="btn btn-primary">Logout</a>
                    <?php else: ?>
                        <a href="signin.php" class="btn btn-outline-primary">Sign In</a>
                        <a href="signup.php" class="btn btn-primary">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Carousel Section -->
    <div id="heroCarousel" class="carousel slide hero-carousel" data-bs-ride="carousel">
        <div class="carousel-indicators">
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="0" class="active"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="1"></button>
            <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="2"></button>
        </div>
        <div class="carousel-inner">
            <div class="carousel-item active" style="background-image: url('https://images.unsplash.com/photo-1583337130417-3346a1be7dee?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80');">
                <div class="carousel-overlay">
                    <div class="carousel-content">
                        <h1>Find Your Perfect Companion at <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></h1>
                        <p>Discover premium dog houses and adorable dogs looking for their forever homes.</p>
                        <a href="#dogs" class="btn btn-primary btn-lg">Browse Dogs</a>
                    </div>
                </div>
            </div>
            <div class="carousel-item" style="background-image: url('https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80');">
                <div class="carousel-overlay">
                    <div class="carousel-content">
                        <h1>Quality Dog Houses for Your Furry Friends</h1>
                        <p>Built with premium materials for comfort, durability, and style.</p>
                        <a href="products.php" class="btn btn-primary btn-lg">Explore Products</a>
                    </div>
                </div>
            </div>
            <div class="carousel-item" style="background-image: url('https://images.unsplash.com/photo-1559190394-df5a28aab5c5?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1920&q=80');">
                <div class="carousel-overlay">
                    <div class="carousel-content">
                        <h1>Expert Pet Care Advice</h1>
                        <p>Get professional guidance on how to care for your new companion.</p>
                        <a href="#contact" class="btn btn-primary btn-lg">Contact Us</a>
                    </div>
                </div>
            </div>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
            <span class="visually-hidden">Previous</span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
            <span class="visually-hidden">Next</span>
        </button>
    </div>

    <!-- Featured Dogs Section -->
    <?php if ($dogsTableExists && !empty($featuredDogs)): ?>
        <section id="dogs" class="dogs-section">
            <div class="container">
                <div class="section-title" data-aos="fade-up">
                    <h2>Featured Dogs</h2>
                    <p>Meet our adorable dogs looking for their forever homes</p>
                </div>
                
                <div class="row">
                    <?php foreach ($featuredDogs as $index => $dog): ?>
                        <div class="col-lg-4 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $index * 100; ?>">
                            <div class="dog-card">
                                <div class="card-img-wrapper">
                                    <?php if (!empty($dog['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($dog['image_url']); ?>" alt="<?php echo htmlspecialchars($dog['name']); ?>">
                                    <?php else: ?>
                                        <img src="https://via.placeholder.com/600x400?text=No+Image" alt="No Image">
                                    <?php endif; ?>
                                    <div class="overlay"></div>
                                </div>
                                <div class="card-body">
                                    <h3 class="card-title"><?php echo htmlspecialchars($dog['name']); ?></h3>
                                    <div class="dog-info">
                                        <span><i class="fas fa-paw"></i> Breed: <?php echo htmlspecialchars($dog['breed']); ?></span>
                                        <span><i class="fas fa-birthday-cake"></i> Age: <?php echo htmlspecialchars($dog['age']); ?></span>
                                        <span><i class="fas fa-star"></i> Traits: <?php echo htmlspecialchars(substr($dog['trait'], 0, 60)) . (strlen($dog['trait']) > 60 ? '...' : ''); ?></span>
                                    </div>
                                    <div class="price">$<?php echo number_format($dog['price'], 2); ?></div>
                                    <?php if ($loggedIn): ?>
                                        <a href="user/view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-primary">View Details</a>
                                    <?php else: ?>
                                        <a href="view_dog.php?id=<?php echo $dog['dog_id']; ?>" class="btn btn-primary">View Details</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center" data-aos="fade-up">
                    <a href="dogs.php" class="btn btn-outline-primary view-all-btn">View All Dogs</a>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <!-- Dog House Categories -->
    <section class="categories-section">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Dog House Categories</h2>
                <p>Find the perfect home for your furry friend</p>
            </div>
            
            <div class="row">
                <div class="col-md-4" data-aos="fade-up">
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1581888227599-779811939961?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Small Dog Houses">
                        <div class="overlay">
                            <h3 class="category-title">Small Dog Houses</h3>
                            <p class="category-desc">Perfect for small breeds and puppies. Cozy and comfortable designs.</p>
                            <a href="products.php?category=small" class="btn btn-primary">Explore</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1601758124510-52d02ddb7cbd?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Medium Dog Houses">
                        <div class="overlay">
                            <h3 class="category-title">Medium Dog Houses</h3>
                            <p class="category-desc">Spacious houses for medium-sized breeds with added features.</p>
                            <a href="products.php?category=medium" class="btn btn-primary">Explore</a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="category-card">
                        <img src="https://images.unsplash.com/photo-1583511655857-d19b40a7a54e?ixlib=rb-4.0.3&auto=format&fit=crop&w=1200&q=80" alt="Large Dog Houses">
                        <div class="overlay">
                            <h3 class="category-title">Large Dog Houses</h3>
                            <p class="category-desc">Premium houses for large breeds with extra durability and space.</p>
                            <a href="products.php?category=large" class="btn btn-primary">Explore</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Choose Us Section -->
    <section class="why-choose">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Why Choose Us</h2>
                <p>Discover what makes us the premier destination for dog lovers</p>
            </div>
            
            <div class="row">
                <div class="col-lg-3 col-md-6" data-aos="fade-up">
                    <div class="why-item">
                        <div class="icon-wrapper">
                            <i class="fas fa-medal"></i>
                        </div>
                        <h4>Quality Assurance</h4>
                        <p>All our dog houses are built using premium, durable materials to withstand all weather conditions.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="100">
                    <div class="why-item">
                        <div class="icon-wrapper">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <h4>Fast Delivery</h4>
                        <p>Quick shipping and hassle-free delivery to your doorstep, with real-time tracking available.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="200">
                    <div class="why-item">
                        <div class="icon-wrapper">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h4>Expert Support</h4>
                        <p>Our team of pet specialists is always ready to help you with any questions or concerns.</p>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="300">
                    <div class="why-item">
                        <div class="icon-wrapper">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h4>Satisfaction Guarantee</h4>
                        <p>If you're not completely satisfied, we offer a 30-day money-back guarantee on all products.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Us Section -->
    <section id="about" class="about-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6" data-aos="fade-right">
                    <div class="about-image">
                        <img src="https://images.unsplash.com/photo-1601758125946-6ec2ef64daf8?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=600&q=80" class="img-fluid" alt="About Us">
                    </div>
                </div>
                <div class="col-lg-6" data-aos="fade-left">
                    <div class="about-content">
                        <h2>Our Story</h2>
                        <p>At <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>, our journey began with a simple mission: to provide exceptional homes for pets and help connect loving families with their perfect companions.</p>
                        
                        <p>Founded in <?php echo htmlspecialchars($companyInfo['established_year'] ?? '2010'); ?> and based in <?php echo htmlspecialchars($companyInfo['city'] ?? 'Oklahoma City'); ?>, our team consists of passionate animal lovers who understand the special bond between pets and their owners.</p>
                        
                        <p>We take pride in crafting comfortable, durable, and stylish dog houses that prioritize your pet's wellbeing. Additionally, our adoption program has helped hundreds of dogs find their forever homes with carefully screened, loving families.</p>
                        
                        <div class="signature">Doghouse Market</div>
                        
                        <a href="about.php" class="btn btn-primary">Learn More About Us</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Us Section -->
    <section id="contact" class="contact-section">
        <div class="container">
            <div class="section-title" data-aos="fade-up">
                <h2>Contact Us</h2>
                <p>Have questions or need assistance? We're here to help!</p>
            </div>
            
            <div class="row">
                <div class="col-lg-7" data-aos="fade-up">
                    <div class="contact-form">
                        <form action="process_contact.php" method="post" id="contactForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="name">Your Name</label>
                                        <input type="text" class="form-control" id="name" name="name" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" required>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="subject">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject">
                            </div>
                            <div class="form-group">
                                <label for="message">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Send Message</button>
                        </form>
                    </div>
                </div>
                <div class="col-lg-5" data-aos="fade-up" data-aos-delay="100">
                    <div class="contact-info">
                        <h3>Get In Touch</h3>
                        <p>We'd love to hear from you! Contact us using the form or through any of the methods below:</p>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="contact-info-content">
                                <h5>Our Location</h5>
                                <p><?php echo htmlspecialchars($companyInfo['address'] ?? 'Oklahoma City, OK 73149'); ?><?php if(!empty($companyInfo['postal_code'])): ?>, <?php echo htmlspecialchars($companyInfo['postal_code']); ?><?php endif; ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="contact-info-content">
                                <h5>Email Address</h5>
                                <p><?php echo htmlspecialchars($companyInfo['email_general'] ?? 'atimalothbrok@gmail.com'); ?></p>
                            </div>
                        </div>
                        
                        <div class="contact-item">
                            <div class="contact-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="contact-info-content">
                                <h5>Business Hours</h5>
                                <p><?php echo $companyInfo['business_hours'] ?? 'Monday - Friday: 8:00am - 4:30pm<br>Saturday: 10:00am - 3:00pm<br>Sunday: Closed'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-lg-3 col-md-6">
                    <div class="footer-content">
                        <h5><?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?></h5>
                        <p><?php echo htmlspecialchars(substr($companyInfo['description'] ?? 'We provide premium dog houses and connect loving families with their perfect canine companions.', 0, 120)); ?>...</p>
                        <div class="social-links">
                            <a href="#"><i class="fab fa-facebook-f"></i></a>
                            <a href="#"><i class="fab fa-twitter"></i></a>
                            <a href="#"><i class="fab fa-instagram"></i></a>
                            <a href="#"><i class="fab fa-pinterest"></i></a>
                        </div>
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Quick Links</h5>
                    <ul class="footer-links">
                        <li><a href="index.php"><i class="fas fa-angle-right"></i> Home</a></li>
                        <li><a href="dogs.php"><i class="fas fa-angle-right"></i> Dogs</a></li>
                        <li><a href="products.php"><i class="fas fa-angle-right"></i> Products</a></li>
                        <li><a href="#about"><i class="fas fa-angle-right"></i> About Us</a></li>
                        <li><a href="#contact"><i class="fas fa-angle-right"></i> Contact Us</a></li>
                        <li><a href="privacy.php"><i class="fas fa-angle-right"></i> Privacy Policy</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Our Services</h5>
                    <ul class="footer-links">
                        <li><a href="#"><i class="fas fa-angle-right"></i> Dog Adoption</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i> Custom Dog Houses</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i> Installation Services</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i> Maintenance & Repair</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i> Dog House Accessories</a></li>
                        <li><a href="#"><i class="fas fa-angle-right"></i> Pet Care Advice</a></li>
                    </ul>
                </div>
                <div class="col-lg-3 col-md-6">
                    <h5>Newsletter</h5>
                    <p>Subscribe to our newsletter for updates on new dogs, products, and special offers.</p>
                    <div class="newsletter-form">
                        <input type="email" class="form-control" placeholder="Your email address">
                        <button type="submit" class="btn btn-primary">Subscribe</button>
                    </div>
                    <ul class="footer-contact list-unstyled mt-4">
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($companyInfo['address'] ?? 'Oklahoma City, OK 73149'); ?><?php if(!empty($companyInfo['postal_code'])): ?>, <?php echo htmlspecialchars($companyInfo['postal_code']); ?><?php endif; ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($companyInfo['email_general'] ?? 'atimalothbrok@gmail.com'); ?></li>
                        <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($companyInfo['phone'] ?? 'Not Available'); ?></li>
                    </ul>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($companyInfo['company_name'] ?? 'Doghouse Market'); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Back To Top Button -->
    <a href="#" class="back-to-top"><i class="fas fa-arrow-up"></i></a>

    <!-- Bootstrap JS with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- AOS Animation Library -->
    <script src="https://cdn.jsdelivr.net/npm/aos@2.3.4/dist/aos.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true,
            offset: 100
        });
        
        // Back to top button functionality
        const backToTopButton = document.querySelector('.back-to-top');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.add('active');
            } else {
                backToTopButton.classList.remove('active');
            }
        });
        
        backToTopButton.addEventListener('click', (e) => {
            e.preventDefault();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Form validation
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                let valid = true;
                const name = document.getElementById('name');
                const email = document.getElementById('email');
                const message = document.getElementById('message');
                
                // Reset validation
                [name, email, message].forEach(field => {
                    field.classList.remove('is-invalid');
                });
                
                // Validate fields
                if (name.value.trim() === '') {
                    valid = false;
                    name.classList.add('is-invalid');
                }
                
                if (email.value.trim() === '' || !email.value.includes('@')) {
                    valid = false;
                    email.classList.add('is-invalid');
                }
                
                if (message.value.trim() === '') {
                    valid = false;
                    message.classList.add('is-invalid');
                }
                
                if (!valid) {
                    e.preventDefault();
                }
            });
        }
        
        // Mobile navigation behavior
        const navLinks = document.querySelectorAll('.navbar-nav .nav-link');
        const navbarCollapse = document.querySelector('.navbar-collapse');
        
        navLinks.forEach((link) => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 992) {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }
            });
        });
        
        // Navbar scroll behavior
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.style.padding = '8px 0';
                navbar.style.boxShadow = '0 2px 20px rgba(0, 0, 0, 0.1)';
            } else {
                navbar.style.padding = '15px 0';
                navbar.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
            }
        });
    </script>
</body>
</html>
