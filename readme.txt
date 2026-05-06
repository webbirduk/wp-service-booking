=== BooCommerce - Appointment Booking & Service Scheduling ===
Contributors: arafatrahmanbd, webbird
Tags: booking, appointments, scheduling, salon booking, reservation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Boost your business with BooCommerce, the most powerful and intuitive appointment booking and service scheduling plugin for WordPress. 

== Description ==

**BooCommerce** is a premium-grade scheduling solution designed to transform how you manage clients, appointments, and payments. Whether you run a hair salon, a medical clinic, a law firm, or a fitness studio, BooCommerce provides a seamless, high-performance experience that converts visitors into loyal customers.

Our plugin is built with a **Mobile-First Design Philosophy**, ensuring that your booking wizard looks stunning on iPhones, Androids, and desktops alike. Stop losing revenue to clunky scheduling tools and switch to the speed and elegance of BooCommerce.

### 🚀 Key Features for Business Growth:
*   **Multi-Service Booking**: Allow clients to book multiple services in a single checkout session.
*   **Intelligent Staff Management**: Assign specific staff members to services, manage their working hours, and track their performance.
*   **Secure Stripe Payments**: Accept credit card payments instantly with our built-in Stripe integration.
*   **Modern Client CRM**: Keep a detailed directory of your clients, their booking history, and contact details.
*   **Dynamic Design Engine**: Customize every color, font, and layout to match your brand perfectly without writing a single line of code.
*   **Automated Email Notifications**: Send professional, responsive HTML emails for booking confirmations, updates, and account details.
*   **Developer Scalability Engine**: Built for developers with 20+ Actions and Filters for infinite extensibility.

### 💼 Perfect For:
*   **Beauty & Wellness**: Salons, Spas, Barbers, and Tattoo Artists.
*   **Healthcare**: Doctors, Dentists, Physiotherapists, and Clinics.
*   **Professional Services**: Lawyers, Consultants, and Coaches.
*   **Education**: Tutors, Music Teachers, and Language Schools.
*   **Home Services**: Cleaners, Plumbers, and Electricians.

== Installation ==

Installing BooCommerce is simple and takes less than 2 minutes.

1.  **Upload**: Download the plugin and upload the `boocommerce` folder to the `/wp-content/plugins/` directory of your WordPress site.
2.  **Activate**: Navigate to the 'Plugins' menu in your WordPress dashboard and click 'Activate' next to BooCommerce.
3.  **Setup Wizard**: Go to the new **BooCommerce** menu in your sidebar.
4.  **Create Services**: Add your first service under the 'Manage Services' tab.
5.  **Add Staff**: Add your professionals in the 'Staff Management' tab.
6.  **Embed**: Use the shortcode `[bc_booking_widget]` on any page or post to start taking bookings!

== Shortcode Guide ==

BooCommerce provides a powerful shortcode engine to help you display your services and booking flows exactly where you need them.

### 01. Primary Booking Widget
The main booking flow for your customers.
`[bc_booking_widget]`

### 02. Service Display Showcase
Embed your services anywhere on your site. Clients can view service details and jump directly into the booking flow.

*   **Show All Services**: `[bc_services]`
*   **Override Layout (Carousel)**: `[bc_services layout="carousel"]`
*   **Specific Service IDs**: `[bc_services ids="1,5,8"]`
*   **Combined Power (IDs + Carousel)**: `[bc_services ids="2,4" layout="carousel"]`
*   **Category + Grid Layout**: `[bc_services category="Hair" layout="grid"]`

### 03. Client Dashboard
The secure portal where clients manage their profile and history.
`[bc_client_dashboard]`

### 04. Mini Basket Widget
Display the selected services count and a "Book Now" trigger.
`[bc_basket]`

== Frequently Asked Questions ==

= Does BooCommerce support online payments? =
Yes! BooCommerce comes with a native Stripe integration that allows you to accept all major credit cards securely. You can enable or disable payments per service.

= Can I manage multiple staff members with different schedules? =
Absolutely. You can add as many staff members as you like, assign them to specific services, and even set individual holidays or time-off.

= Is the booking wizard mobile-friendly? =
Yes, BooCommerce is built with modern CSS and JavaScript to be fully responsive. It provides an app-like experience on all mobile devices.

= Can clients manage their own appointments? =
Yes. Every client gets a secure dashboard (via the `[bc_client_dashboard]` shortcode) where they can view their booking history and update their profile.

= Is BooCommerce developer-friendly? =
It's a developer's dream. We provide extensive hooks (Actions and Filters) so you can extend the plugin's functionality, create custom payment gateways, or modify the UI logic.

= Does it support multi-service bookings? =
Yes, BooCommerce features a "Basket" mode where customers can select multiple services before proceeding to the checkout and payment.

== Screenshots ==
1. The modern booking wizard interface.
2. Admin dashboard with analytics and insights.
3. Staff management and scheduling view.
4. Professional client CRM profile view.

== Changelog ==

= 1.0.0 =
*   Initial stable release.
*   Integrated Stripe Payment Gateway.
*   Added Client CRM & Directory.
*   Implemented Multi-Service "Basket" logic.
*   Added Developer Scalability Engine with 20+ hooks.
