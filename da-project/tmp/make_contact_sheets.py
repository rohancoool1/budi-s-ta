from pathlib import Path

from PIL import Image, ImageOps, ImageDraw


root = Path(r"C:\Users\Administrator\Documents\Rogan\budi\da-project\tmp\guide_render")
pages = sorted((root / "pages").glob("page-*.png"))
out_dir = root / "contact_sheets"
out_dir.mkdir(parents=True, exist_ok=True)
gap = 28
label_height = 42

for start in range(0, len(pages), 2):
    pair = pages[start:start + 2]
    images = [Image.open(path).convert("RGB") for path in pair]
    width = max(image.width for image in images)
    height = max(image.height for image in images)
    sheet = Image.new("RGB", (width * len(images) + gap * (len(images) + 1), height + label_height + gap * 2), "#e8e8e8")
    draw = ImageDraw.Draw(sheet)
    for offset, image in enumerate(images):
        x = gap + offset * (width + gap)
        y = gap + label_height
        sheet.paste(image, (x, y))
        page_number = start + offset + 1
        draw.text((x + 4, gap + 4), f"Page {page_number}", fill="#111111")
    sheet.save(out_dir / f"sheet-{start // 2 + 1:02d}.png")

print(f"Created {(len(pages) + 1) // 2} two-page contact sheets at {out_dir}")
