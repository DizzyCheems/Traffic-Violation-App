#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
ocr_plate.py
Auto-detects Tesseract on Windows – no PATH required!
"""

import cv2
import pytesseract
import sys
import json
import os
import shutil

# ------------------------------------------------------------------
# 1. SET YOUR TESSERACT PATH HERE (EDIT ONLY THIS LINE IF NEEDED)
# ------------------------------------------------------------------
# Example: r'C:\Program Files\Tesseract-OCR\tesseract.exe'
#          r'C:\Users\JUSTINE\AppData\Local\Tesseract-OCR\tesseract.exe'
MANUAL_TESSERACT_PATH = r''  # ←←← EDIT THIS LINE IF AUTO-DETECT FAILS

# ------------------------------------------------------------------
# 2. AUTO-DETECT TESSERACT (common locations)
# ------------------------------------------------------------------
def find_tesseract():
    # Try manual path first
    if MANUAL_TESSERACT_PATH and os.path.isfile(MANUAL_TESSERACT_PATH):
        return MANUAL_TESSERACT_PATH

    # Common install paths
    candidates = [
        r'C:\Program Files\Tesseract-OCR\tesseract.exe',
        r'C:\Program Files (x86)\Tesseract-OCR\tesseract.exe',
        os.path.expanduser(r'~\AppData\Local\Tesseract-OCR\tesseract.exe'),
        os.path.expanduser(r'~\AppData\Local\Programs\Tesseract-OCR\tesseract.exe'),
    ]
    for path in candidates:
        if os.path.isfile(path):
            return path

    # Last resort: check if in PATH
    if shutil.which('tesseract'):
        return 'tesseract'

    return None

tesseract_exe = find_tesseract()

if not tesseract_exe:
    print(json.dumps({
        "error": "Tesseract not found!",
        "help": (
            "Install from: https://github.com/UB-Mannheim/tesseract/wiki\n"
            "Then edit ocr_plate.py and set MANUAL_TESSERACT_PATH = r'C:\\your\\path\\tesseract.exe'"
        )
    }))
    sys.exit(1)

# Set the path
pytesseract.pytesseract.tesseract_cmd = tesseract_exe

# ------------------------------------------------------------------
# 3. PATHS (relative to script)
# ------------------------------------------------------------------
BASE_DIR   = os.path.dirname(os.path.abspath(__file__))
UPLOAD_DIR = os.path.abspath(os.path.join(BASE_DIR, '..', 'uploads'))
OUTPUT_DIR = os.path.abspath(os.path.join(BASE_DIR, '..', 'outputs'))
os.makedirs(OUTPUT_DIR, exist_ok=True)

# ------------------------------------------------------------------
# 4. OCR + EDGE DETECTION
# ------------------------------------------------------------------
def detect_and_ocr(input_path, output_path):
    if not os.path.exists(input_path):
        return {"error": "File not found", "path": input_path}

    img = cv2.imread(input_path)
    if img is None:
        return {"error": "OpenCV failed to read image"}

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
    blurred = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(blurred, 50, 150)

    contours, _ = cv2.findContours(edges, cv2.RETR_TREE, cv2.CHAIN_APPROX_SIMPLE)
    contours = sorted(contours, key=cv2.contourArea, reverse=True)[:10]

    plate_found = False
    text = ""

    for cnt in contours:
        peri = cv2.arcLength(cnt, True)
        approx = cv2.approxPolyDP(cnt, 0.02 * peri, True)
        if len(approx) == 4:
            x, y, w, h = cv2.boundingRect(approx)
            aspect = w / float(h)
            if 2.0 < aspect < 6.0 and w > 100 and h > 30:
                plate = gray[y:y+h, x:x+w]
                plate = cv2.resize(plate, None, fx=2, fy=2, interpolation=cv2.INTER_CUBIC)
                _, plate = cv2.threshold(plate, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

                cfg = r'--oem 3 --psm 7 -c tessedit_char_whitelist=ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789 '
                text = pytesseract.image_to_string(plate, config=cfg).strip()

                cv2.rectangle(img, (x, y), (x+w, y+h), (0, 255, 0), 3)
                cv2.putText(img, text, (x, y-10), cv2.FONT_HERSHEY_SIMPLEX, 0.9, (0, 255, 0), 2)

                plate_found = True
                break

    if not plate_found:
        cfg = r'--o manifestó 3 --psm 3'
        text = pytesseract.image_to_string(gray, config=cfg).strip()
        cv2.putText(img, "No plate", (10, 30), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 2)

    cv2.imwrite(output_path, img)
    return {
        "text": text or "No text",
        "plate_found": plate_found,
        "output_image": os.path.basename(output_path),
        "tesseract_used": tesseract_exe
    }

# ------------------------------------------------------------------
# 5. RUN
# ------------------------------------------------------------------
if __name__ == "__main__":
    if len(sys.argv) != 3:
        print(json.dumps({"error": "Usage: ocr_plate.py <input> <output>"}))
        sys.exit(1)

    result = detect_and_ocr(sys.argv[1], sys.argv[2])
    print(json.dumps(result))