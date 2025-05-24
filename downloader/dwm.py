from selenium import webdriver
from selenium.webdriver.chrome.options import Options
from bs4 import BeautifulSoup
import os
import time
import requests
import tkinter as tk
from tkinter import messagebox

def download_images_oremanga(page_url, folder="downloaded_images"):
    try:
        options = Options()
        options.add_argument("--headless")
        driver = webdriver.Chrome(options=options)
        print(f"Opening {page_url}...")
        driver.get(page_url)
        time.sleep(3)

        soup = BeautifulSoup(driver.page_source, "html.parser")
        imgs = soup.select(".reader-area-main img")
        if not imgs:
            driver.quit()
            return "No images found."

        os.makedirs(folder, exist_ok=True)
        downloaded = 0

        for i, img in enumerate(imgs):
            img_url = img.get("src")
            if not img_url:
                continue
            filename = os.path.join(folder, f"image_{i+1}.jpg")
            print(f"Downloading {img_url} as {filename}")
            headers = {"Referer": page_url}
            img_resp = requests.get(img_url, headers=headers)
            if img_resp.status_code == 200:
                with open(filename, "wb") as f:
                    f.write(img_resp.content)
                downloaded += 1
            else:
                print(f"Failed to download {img_url} (status: {img_resp.status_code})")
        driver.quit()
        return f"Downloaded {downloaded} images to '{folder}'!"
    except Exception as e:
        return f"Error: {e}"

def download_images_gomanga(page_url, folder="downloaded_images"):
    try:
        options = Options()
        options.add_argument("--headless")
        driver = webdriver.Chrome(options=options)
        print(f"Opening {page_url}...")
        driver.get(page_url)
        time.sleep(3)

        soup = BeautifulSoup(driver.page_source, "html.parser")
        imgs = soup.select("#readerarea img.ts-main-image")
        if not imgs:
            driver.quit()
            return "No images found."

        os.makedirs(folder, exist_ok=True)
        downloaded = 0

        for i, img in enumerate(imgs):
            img_url = img.get("src")
            if not img_url:
                continue
            filename = os.path.join(folder, f"image_{i+1}.jpg")
            print(f"Downloading {img_url} as {filename}")
            img_resp = requests.get(img_url)  # No referer header
            if img_resp.status_code == 200:
                with open(filename, "wb") as f:
                    f.write(img_resp.content)
                downloaded += 1
            else:
                print(f"Failed to download {img_url} (status: {img_resp.status_code})")
        driver.quit()
        return f"Downloaded {downloaded} images to '{folder}'!"
    except Exception as e:
        return f"Error: {e}"

def on_download():
    url = url_entry.get().strip()
    folder = folder_entry.get().strip() or "downloaded_images"
    site = site_var.get()
    if not url:
        messagebox.showerror("Input Error", "Please enter a manga chapter URL.")
        return
    download_button.config(state="disabled")
    root.update()
    if site == "GoManga":
        status = download_images_gomanga(url, folder)
    else:
        status = download_images_oremanga(url, folder)
    download_button.config(state="normal")
    messagebox.showinfo("Status", status)

# --- GUI part ---
root = tk.Tk()
root.title("Manga Image Downloader (Selenium)")

tk.Label(root, text="Manga Site:").grid(row=0, column=0, padx=10, pady=5, sticky='e')
site_var = tk.StringVar(value="Oremanga")
site_menu = tk.OptionMenu(root, site_var, "Oremanga", "GoManga")
site_menu.grid(row=0, column=1, padx=10, pady=5, sticky='w')

tk.Label(root, text="Manga Chapter URL:").grid(row=1, column=0, padx=10, pady=5, sticky='e')
url_entry = tk.Entry(root, width=50)
url_entry.grid(row=1, column=1, padx=10, pady=5)

tk.Label(root, text="Folder Name:").grid(row=2, column=0, padx=10, pady=5, sticky='e')
folder_entry = tk.Entry(root, width=30)
folder_entry.insert(0, "downloaded_images")
folder_entry.grid(row=2, column=1, padx=10, pady=5, sticky='w')

download_button = tk.Button(root, text="Download Images", command=on_download)
download_button.grid(row=3, column=0, columnspan=2, pady=15)

root.mainloop()
