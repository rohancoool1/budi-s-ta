from pathlib import Path

import pypdfium2 as pdfium


pdf_path = Path(r"C:\Users\Administrator\Documents\Rogan\budi\da-project\tmp\guide_render\Modul_Alur_Lengkap_Proyek_HOMCUTS.pdf")
output_dir = Path(r"C:\Users\Administrator\Documents\Rogan\budi\da-project\tmp\guide_render\pages")
output_dir.mkdir(parents=True, exist_ok=True)

pdf = pdfium.PdfDocument(str(pdf_path))
for index in range(len(pdf)):
    page = pdf[index]
    bitmap = page.render(scale=1.4)
    image = bitmap.to_pil()
    image.save(output_dir / f"page-{index + 1:02d}.png")

print(f"Rendered {len(pdf)} pages into {output_dir}")
