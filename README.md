# Manga Image Downloader & PHP Auth System

This project combines a simple PHP authentication system with an admin dashboard, plus a Python GUI tool for downloading manga images from popular manga reading sites.

## Features

### PHP Web App

- User registration (`register.php`)
- Login/logout (`login.php`, `logout.php`)
- Admin dashboard and points management (`admin.php`, `admin_point.php`)
- Database connection in `db.php`

### Python Manga Downloader (`dwm.py`)

- Download all images from a manga chapter on **Oremanga** or **GoManga**
- User-friendly GUI (Tkinter)
- Uses Selenium and BeautifulSoup
- Saves images to a folder of your choice

---

## Getting Started

### PHP Web App

1. **Requirements:** PHP 7+, MySQL, and a web server (Apache, Nginx, etc.)
2. Place the PHP files on your server.
3. Update `db.php` with your database details.
4. Create the required user/admin tables in your MySQL database.
5. Access via browser to register and log in.

### Python Manga Downloader

1. **Requirements:**  
   - Python 3.7+  
   - Selenium (`pip install selenium`)  
   - BeautifulSoup (`pip install beautifulsoup4`)  
   - Requests (`pip install requests`)  
   - Tkinter (included with most Python installs)  
   - Chrome WebDriver (matching your Chrome version)
2. Run the script:
    ```bash
    python dwm.py
    ```
3. Use the GUI:  
   - Select the site (Oremanga/GoManga)
   - Paste the manga chapter URL
   - Click "Download Images"

---

## File List

- `index.php`
- `login.php`
- `logout.php`
- `register.php`
- `admin.php`
- `admin_point.php`
- `db.php`
- `dwm.py`

---

## Notes

- For learning and personal use only. Respect the manga sites’ terms of service.
- The PHP system is a basic template—add security improvements before using in production (like password hashing and input validation).

---

## License

MIT License

---

