const imageInputs = [...document.querySelectorAll('input[type="file"][data-image-editor]')];

if (imageInputs.length) {
    const modal = document.createElement('div');
    modal.className = 'image-editor hidden';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-labelledby', 'image-editor-title');
    modal.innerHTML = `
        <div class="image-editor__backdrop" data-editor-cancel></div>
        <section class="image-editor__panel">
            <header class="image-editor__header">
                <div>
                    <p class="image-editor__eyebrow">Editor foto</p>
                    <h2 id="image-editor-title">Atur potongan gambar</h2>
                </div>
                <button class="image-editor__close" type="button" data-editor-cancel aria-label="Tutup editor">×</button>
            </header>
            <div class="image-editor__body">
                <div class="image-editor__stage" data-editor-stage>
                    <canvas data-editor-canvas aria-label="Pratinjau area crop"></canvas>
                    <p class="image-editor__drag-hint">Geser foto untuk mengatur posisi</p>
                </div>
                <aside class="image-editor__controls">
                    <fieldset>
                        <legend>Rasio foto</legend>
                        <div class="image-editor__ratio-list">
                            <button type="button" data-editor-ratio="original">Asli</button>
                            <button type="button" data-editor-ratio="1">1 : 1</button>
                            <button type="button" data-editor-ratio="0.8">4 : 5</button>
                            <button type="button" data-editor-ratio="1.333333">4 : 3</button>
                            <button type="button" data-editor-ratio="1.777778">16 : 9</button>
                        </div>
                    </fieldset>
                    <label class="image-editor__range-label" for="image-editor-zoom">
                        <span>Perbesaran</span><output data-editor-zoom-output>100%</output>
                    </label>
                    <input id="image-editor-zoom" data-editor-zoom type="range" min="100" max="300" value="100" step="1">
                    <div class="image-editor__tool-row">
                        <button type="button" data-editor-rotate="-90" title="Putar ke kiri">↶ Putar kiri</button>
                        <button type="button" data-editor-rotate="90" title="Putar ke kanan">Putar kanan ↷</button>
                    </div>
                    <p class="image-editor__details" data-editor-details></p>
                    <p class="image-editor__note">Bagian di dalam bingkai adalah foto yang akan disimpan. Hasil diperkecil maksimal 1.600 piksel agar unggahan tetap ringan.</p>
                </aside>
            </div>
            <footer class="image-editor__footer">
                <button class="image-editor__secondary" type="button" data-editor-cancel>Batal</button>
                <button class="image-editor__primary" type="button" data-editor-apply>Terapkan crop <span>✓</span></button>
            </footer>
        </section>
    `;
    document.body.append(modal);

    const canvas = modal.querySelector('[data-editor-canvas]');
    const context = canvas.getContext('2d', { alpha: false });
    const stage = modal.querySelector('[data-editor-stage]');
    const zoomInput = modal.querySelector('[data-editor-zoom]');
    const zoomOutput = modal.querySelector('[data-editor-zoom-output]');
    const details = modal.querySelector('[data-editor-details]');
    const applyButton = modal.querySelector('[data-editor-apply]');
    const ratioButtons = [...modal.querySelectorAll('[data-editor-ratio]')];

    let activeInput = null;
    let activeFile = null;
    let sourceImage = null;
    let objectUrl = null;
    let aspectRatio = 1;
    let rotation = 0;
    let zoom = 1;
    let offsetX = 0;
    let offsetY = 0;
    let pointer = null;

    const orientedSize = () => {
        const sideways = Math.abs(rotation % 180) === 90;
        return {
            width: sideways ? sourceImage.naturalHeight : sourceImage.naturalWidth,
            height: sideways ? sourceImage.naturalWidth : sourceImage.naturalHeight,
        };
    };

    const setCanvasSize = () => {
        const maxSide = 1600;
        if (aspectRatio >= 1) {
            canvas.width = maxSide;
            canvas.height = Math.round(maxSide / aspectRatio);
        } else {
            canvas.height = maxSide;
            canvas.width = Math.round(maxSide * aspectRatio);
        }
        stage.style.setProperty('--editor-aspect', String(aspectRatio));
    };

    const baseScale = () => {
        const size = orientedSize();
        return Math.max(canvas.width / size.width, canvas.height / size.height);
    };

    const constrainOffsets = () => {
        const size = orientedSize();
        const scale = baseScale() * zoom;
        const width = size.width * scale;
        const height = size.height * scale;
        const limitX = Math.max(0, (width - canvas.width) / 2);
        const limitY = Math.max(0, (height - canvas.height) / 2);
        offsetX = Math.max(-limitX, Math.min(limitX, offsetX));
        offsetY = Math.max(-limitY, Math.min(limitY, offsetY));
    };

    const draw = () => {
        if (!sourceImage) return;
        constrainOffsets();
        const scale = baseScale() * zoom;
        context.save();
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.translate((canvas.width / 2) + offsetX, (canvas.height / 2) + offsetY);
        context.rotate(rotation * Math.PI / 180);
        context.scale(scale, scale);
        context.drawImage(sourceImage, -sourceImage.naturalWidth / 2, -sourceImage.naturalHeight / 2);
        context.restore();
        details.textContent = `${sourceImage.naturalWidth} × ${sourceImage.naturalHeight} px → ${canvas.width} × ${canvas.height} px`;
    };

    const selectRatio = (value, resetPosition = true) => {
        aspectRatio = value === 'original'
            ? sourceImage.naturalWidth / sourceImage.naturalHeight
            : Number(value);
        ratioButtons.forEach((button) => button.classList.toggle('is-active', button.dataset.editorRatio === String(value)));
        if (resetPosition) {
            offsetX = 0;
            offsetY = 0;
            zoom = 1;
            zoomInput.value = '100';
            zoomOutput.value = '100%';
        }
        setCanvasSize();
        draw();
    };

    const closeEditor = (keepSelection) => {
        modal.classList.add('hidden');
        document.body.classList.remove('image-editor-open');
        if (!keepSelection && activeInput) activeInput.value = '';
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = null;
        activeInput = null;
        activeFile = null;
        sourceImage = null;
        pointer = null;
    };

    const openEditor = (input, file) => {
        if (!file.type.startsWith('image/')) {
            window.alert('File yang dipilih bukan gambar yang didukung.');
            input.value = '';
            return;
        }

        activeInput = input;
        activeFile = file;
        sourceImage = new Image();
        objectUrl = URL.createObjectURL(file);
        sourceImage.onload = () => {
            rotation = 0;
            zoom = 1;
            offsetX = 0;
            offsetY = 0;
            modal.classList.remove('hidden');
            document.body.classList.add('image-editor-open');
            selectRatio(input.dataset.cropAspect || '1');
            modal.querySelector('[data-editor-cancel]').focus();
        };
        sourceImage.onerror = () => {
            window.alert('Foto tidak dapat dibaca. Silakan pilih JPG, PNG, atau WEBP lain.');
            closeEditor(false);
        };
        sourceImage.src = objectUrl;
    };

    const replaceInputFile = (blob) => {
        const baseName = activeFile.name.replace(/\.[^.]+$/, '').replace(/[^a-zA-Z0-9_-]+/g, '-');
        const croppedFile = new File([blob], `${baseName || 'foto'}-crop.jpg`, {
            type: 'image/jpeg',
            lastModified: Date.now(),
        });
        const transfer = new DataTransfer();
        transfer.items.add(croppedFile);
        activeInput.files = transfer.files;

        const wrapper = activeInput.closest('div');
        const preview = wrapper?.querySelector('[data-image-editor-preview]');
        if (preview) {
            const previewUrl = URL.createObjectURL(blob);
            const previousUrl = preview.dataset.generatedPreview;
            if (previousUrl) URL.revokeObjectURL(previousUrl);
            preview.dataset.generatedPreview = previewUrl;
            preview.style.backgroundImage = `url("${previewUrl}")`;
            preview.style.backgroundPosition = '50% 50%';
            preview.style.backgroundSize = 'cover';
            preview.classList.remove('hidden');
        }
        activeInput.dispatchEvent(new CustomEvent('image-editor:applied', { bubbles: true }));
        closeEditor(true);
    };

    imageInputs.forEach((input) => input.addEventListener('change', () => {
        const file = input.files?.[0];
        if (file) openEditor(input, file);
    }));

    ratioButtons.forEach((button) => button.addEventListener('click', () => selectRatio(button.dataset.editorRatio)));

    zoomInput.addEventListener('input', () => {
        zoom = Number(zoomInput.value) / 100;
        zoomOutput.value = `${zoomInput.value}%`;
        draw();
    });

    modal.querySelectorAll('[data-editor-rotate]').forEach((button) => button.addEventListener('click', () => {
        rotation = (rotation + Number(button.dataset.editorRotate) + 360) % 360;
        offsetX = 0;
        offsetY = 0;
        draw();
    }));

    canvas.addEventListener('pointerdown', (event) => {
        pointer = { id: event.pointerId, x: event.clientX, y: event.clientY };
        canvas.setPointerCapture(event.pointerId);
        canvas.classList.add('is-dragging');
    });

    canvas.addEventListener('pointermove', (event) => {
        if (!pointer || pointer.id !== event.pointerId) return;
        const rect = canvas.getBoundingClientRect();
        offsetX += (event.clientX - pointer.x) * (canvas.width / rect.width);
        offsetY += (event.clientY - pointer.y) * (canvas.height / rect.height);
        pointer.x = event.clientX;
        pointer.y = event.clientY;
        draw();
    });

    const releasePointer = (event) => {
        if (!pointer || pointer.id !== event.pointerId) return;
        pointer = null;
        canvas.classList.remove('is-dragging');
    };
    canvas.addEventListener('pointerup', releasePointer);
    canvas.addEventListener('pointercancel', releasePointer);

    canvas.addEventListener('wheel', (event) => {
        event.preventDefault();
        const next = Math.max(1, Math.min(3, zoom + (event.deltaY < 0 ? 0.05 : -0.05)));
        zoom = next;
        zoomInput.value = String(Math.round(zoom * 100));
        zoomOutput.value = `${zoomInput.value}%`;
        draw();
    }, { passive: false });

    modal.querySelectorAll('[data-editor-cancel]').forEach((button) => button.addEventListener('click', () => closeEditor(false)));

    applyButton.addEventListener('click', () => {
        applyButton.disabled = true;
        applyButton.textContent = 'Memproses…';
        canvas.toBlob((blob) => {
            applyButton.disabled = false;
            applyButton.innerHTML = 'Terapkan crop <span>✓</span>';
            if (!blob) {
                window.alert('Crop belum dapat dibuat. Silakan coba kembali.');
                return;
            }
            replaceInputFile(blob);
        }, 'image/jpeg', 0.9);
    });

    document.addEventListener('keydown', (event) => {
        if (modal.classList.contains('hidden')) return;
        if (event.key === 'Escape') closeEditor(false);
    });
}
