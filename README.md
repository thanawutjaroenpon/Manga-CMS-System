Manga Image Downloader & Web Auth System
This project consists of two main parts:

A PHP-based authentication/admin system with registration, login, logout, and admin panels.

A Python GUI tool for downloading manga images from manga reader websites (Oremanga and GoManga) using Selenium and BeautifulSoup.

Features
PHP Web Application
User registration (register.php)

User login (login.php)

User logout (logout.php)

Admin dashboard (admin.php)

Admin points management (admin_point.php)

Centralized database connection (db.php)

Python Manga Downloader (dwm.py)
Download all images from a manga chapter on supported sites.

Supports Oremanga and GoManga.

User-friendly GUI with Tkinter.

Downloads images to a user-specified folder.

Uses Selenium for dynamic content, and BeautifulSoup for HTML parsing.

Getting Started
1. PHP Web Application
Requirements
PHP 7.x or higher

MySQL or MariaDB

Web server (Apache, Nginx, etc.)

Setup
Clone or download this repository.

Place the PHP files in your web server directory.

Create a MySQL database and import your user/admin schema as required.

Update the database credentials in db.php.

Access the site through your browser to use the registration and login system.

2. Manga Downloader (Python)
Requirements
Python 3.7+

Selenium (pip install selenium)

BeautifulSoup (pip install beautifulsoup4)

Requests (pip install requests)

Tkinter (usually included with Python)

Chrome WebDriver (must match your Chrome version and be on your PATH)

Usage
Run dwm.py:

bash
Copy
Edit
python dwm.py
In the GUI:

Select the manga site (Oremanga or GoManga).

Paste the chapter URL.

Optionally, set a folder for downloaded images.

Click "Download Images".

Downloaded images will be saved to the specified folder.

Folder Structure
pgsql
Copy
Edit
.
├── admin.php
├── admin_point.php
├── db.php
├── index.php
├── login.php
├── logout.php
├── register.php
├── dwm.py
Notes
The Python downloader is for educational purposes. Respect the terms of service of manga hosting websites.

The PHP system is a simple template and may need security hardening for production use (e.g., password hashing, input validation, CSRF protection, etc.).

License
MIT License

Credits
Selenium: https://www.selenium.dev/

BeautifulSoup: https://www.crummy.com/software/BeautifulSoup/

Tkinter: Standard Python GUI library