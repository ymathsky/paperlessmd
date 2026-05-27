from PIL import Image, ImageDraw, ImageFont
import os

OUT = r"C:\xampp\htdocs\pd\assets\img"
os.makedirs(OUT, exist_ok=True)

W, H = 860, 540  # standard card ratio

# ── helper: try to load a Windows font ────────────────────────────────────────
def font(size, bold=False):
    names = (
        ["arialbd.ttf", "calibrib.ttf", "verdanab.ttf"] if bold
        else ["arial.ttf", "calibri.ttf", "verdana.ttf"]
    )
    for n in names:
        try:
            return ImageFont.truetype(f"C:/Windows/Fonts/{n}", size)
        except Exception:
            pass
    return ImageFont.load_default()

# ── 1. MEDICARE CARD — FRONT ───────────────────────────────────────────────────
def make_medicare_front():
    img = Image.new("RGB", (W, H), "#FFFFFF")
    d = ImageDraw.Draw(img)

    # top banner — CMS blue
    d.rectangle([0, 0, W, 100], fill="#003087")
    d.text((30, 18), "MEDICARE", fill="white", font=font(52, bold=True))
    d.text((W - 240, 22), "HEALTH INSURANCE", fill="#A0C4FF", font=font(22))

    # red accent stripe
    d.rectangle([0, 100, W, 114], fill="#CC0000")

    # card body
    d.rectangle([0, 114, W, H], fill="#F8FAFF")

    # NAME section
    d.text((40, 140), "NAME", fill="#888888", font=font(18))
    d.text((40, 165), "BETTY  JOHNSON", fill="#003087", font=font(38, bold=True))

    # Medicare Number
    d.text((40, 230), "MEDICARE NUMBER", fill="#888888", font=font(18))
    d.text((40, 255), "1EG4-TE5-MK72", fill="#111111", font=font(34, bold=True))

    # Coverage
    d.text((40, 320), "IS ENTITLED TO", fill="#888888", font=font(18))
    d.rectangle([40, 348, 320, 386], fill="#003087", outline="#003087")
    d.text((52, 354), "HOSPITAL  (PART A)", fill="white", font=font(22, bold=True))
    d.rectangle([340, 348, 560, 386], fill="#003087", outline="#003087")
    d.text((352, 354), "MEDICAL  (PART B)", fill="white", font=font(22, bold=True))

    # Effective date
    d.text((40, 410), "EFFECTIVE DATE", fill="#888888", font=font(18))
    d.text((40, 435), "PART A:  01-01-2009       PART B:  01-01-2009", fill="#333333", font=font(22))

    # footer
    d.rectangle([0, H - 48, W, H], fill="#003087")
    d.text((30, H - 36), "U.S. DEPARTMENT OF HEALTH AND HUMAN SERVICES    Centers for Medicare & Medicaid Services",
           fill="#A0C4FF", font=font(16))

    # decorative eagle watermark (simple circle placeholder)
    d.ellipse([W - 160, 130, W - 40, 250], outline="#D0D8EF", width=3)
    d.text((W - 145, 165), "CMS", fill="#D0D8EF", font=font(30, bold=True))

    img.save(os.path.join(OUT, "betty_medicare_front.jpg"), quality=95)
    print("✓  betty_medicare_front.jpg")


# ── 2. MEDICARE CARD — BACK ────────────────────────────────────────────────────
def make_medicare_back():
    img = Image.new("RGB", (W, H), "#F8FAFF")
    d = ImageDraw.Draw(img)

    # top bar
    d.rectangle([0, 0, W, 70], fill="#003087")
    d.text((30, 16), "MEDICARE — IMPORTANT INFORMATION", fill="white", font=font(28, bold=True))

    # magnetic stripe simulation
    d.rectangle([0, 70, W, 120], fill="#1A1A1A")

    # signature strip
    d.rectangle([40, 135, W - 40, 185], fill="#EFEFEF", outline="#BBBBBB")
    d.text((50, 148), "Authorized Signature:   Betty Johnson", fill="#333333", font=font(22))

    # info blocks
    lines = [
        ("SHOW YOUR CARD", "Show this card to your doctor, hospital, or other health care provider when you",
         "receive Medicare-covered services."),
        ("LOST OR STOLEN CARD?", "Call 1-800-MEDICARE (1-800-633-4227) right away. TTY users: 1-877-486-2048.",
         "You can also visit Medicare.gov to request a replacement card."),
        ("KEEP CARD SAFE", "Do NOT laminate this card. Keep it in a safe place and carry it with you",
         "when you need medical care."),
    ]

    y = 205
    for title, line1, line2 in lines:
        d.text((40, y), title, fill="#CC0000", font=font(20, bold=True))
        d.text((40, y + 26), line1, fill="#333333", font=font(18))
        d.text((40, y + 50), line2, fill="#333333", font=font(18))
        d.line([40, y + 78, W - 40, y + 78], fill="#CCCCCC", width=1)
        y += 90

    # footer
    d.rectangle([0, H - 48, W, H], fill="#003087")
    d.text((30, H - 36), "Medicare.gov   |   1-800-MEDICARE   |   TTY: 1-877-486-2048",
           fill="#A0C4FF", font=font(18))

    img.save(os.path.join(OUT, "betty_medicare_back.jpg"), quality=95)
    print("✓  betty_medicare_back.jpg")


# ── 3. ILLINOIS STATE ID (Government ID) ──────────────────────────────────────
def make_state_id():
    img = Image.new("RGB", (W, H), "#FFFFFF")
    d = ImageDraw.Draw(img)

    # Illinois flag-inspired gradient header (simulate with rectangles)
    for i in range(80):
        shade = int(0 + (i / 80) * 30)
        d.rectangle([0, i, W, i + 1], fill=(0, 48 + shade, 135 + shade))
    d.text((30, 14), "ILLINOIS", fill="white", font=font(44, bold=True))
    d.text((30, 60), "IDENTIFICATION CARD", fill="#A0C4FF", font=font(22))
    d.text((W - 200, 20), "NOT FOR DRIVING", fill="#FFD700", font=font(20, bold=True))

    # blue side accent bar
    d.rectangle([0, 80, 12, H], fill="#003087")

    # photo placeholder box
    d.rectangle([30, 100, 210, 290], fill="#D8E4F0", outline="#003087", width=2)
    d.text((68, 175), "PHOTO", fill="#7090B0", font=font(22, bold=True))

    # DD stripe (decorative lines on right side)
    for i, y in enumerate(range(95, H - 60, 18)):
        d.line([220, y, W - 20, y], fill="#E8EEF8", width=1)

    # DATA fields
    d.text((230, 105), "LAST NAME", fill="#888888", font=font(16))
    d.text((230, 126), "JOHNSON", fill="#003087", font=font(30, bold=True))

    d.text((230, 175), "FIRST NAME         MI", fill="#888888", font=font(16))
    d.text((230, 196), "BETTY", fill="#111111", font=font(28, bold=True))

    d.text((230, 245), "DATE OF BIRTH", fill="#888888", font=font(16))
    d.text((230, 266), "03-14-1948", fill="#111111", font=font(26, bold=True))

    d.text((230, 315), "ADDRESS", fill="#888888", font=font(16))
    d.text((230, 336), "742 EVERGREEN TERRACE", fill="#111111", font=font(22, bold=True))
    d.text((230, 362), "NAPERVILLE, IL  60540", fill="#111111", font=font(22))

    d.text((230, 405), "SEX    HGT     EYE     HAIR", fill="#888888", font=font(16))
    d.text((230, 426), "F       5-04    BRN      GRY", fill="#111111", font=font(22, bold=True))

    # ID number
    d.text((30, 310), "ID NUMBER", fill="#888888", font=font(14))
    d.text((30, 330), "I123-4567-8901", fill="#003087", font=font(18, bold=True))

    # Expiration
    d.text((30, 370), "EXPIRES", fill="#888888", font=font(14))
    d.text((30, 390), "03-14-2030", fill="#CC0000", font=font(18, bold=True))

    # barcode placeholder
    d.rectangle([30, 430, 370, 490], fill="#111111")
    # simulate barcode lines
    for bx in range(35, 365, 6):
        w = 2 if bx % 18 == 0 else 1
        d.rectangle([bx, 432, bx + w, 488], fill="white")
    d.text((380, 448), "ILID-BJ-031448", fill="#333333", font=font(18))

    # state seal placeholder
    d.ellipse([W - 130, H - 130, W - 20, H - 20], fill="#E8EEF8", outline="#003087", width=2)
    d.text((W - 108, H - 88), "IL", fill="#003087", font=font(32, bold=True))
    d.text((W - 116, H - 52), "SEAL", fill="#888888", font=font(14))

    # footer
    d.rectangle([0, H - 48, W, H], fill="#003087")
    d.text((30, H - 36), "Secretary of State   |   Illinois.gov   |   NOT VALID FOR FEDERAL IDENTIFICATION",
           fill="#A0C4FF", font=font(16))

    img.save(os.path.join(OUT, "betty_state_id.jpg"), quality=95)
    print("✓  betty_state_id.jpg")


make_medicare_front()
make_medicare_back()
make_state_id()
print("\nAll 3 cards saved to:", OUT)
