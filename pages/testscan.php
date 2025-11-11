<?php
if (!isset($_GET['embed'])) {
    // STAND-ALONE TEST PAGE
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>OCR – Upload & Read Text (Edge Detection)</title>
        <style>
            body{font-family:Arial;background:#f9f9f9;margin:0;padding:20px}
            #scanner{max-width:500px;margin:auto;background:#fff;padding:20px;border-radius:10px;box-shadow:0 0 10px rgba(0,0,0,.1);text-align:center}
            .dropzone{border:3px dashed #007bff;padding:30px;margin:15px 0;border-radius:8px;background:#f8fbff;cursor:pointer;font-size:1em;color:#555}
            .dropzone.dragover{background:#e3f2fd;border-color:#0056b3}
            #preview img{max-width:100%;max-height:250px;border-radius:6px;margin:10px 0;border:1px solid #ddd}
            button{padding:10px 20px;font-size:16px;background:#007bff;color:#fff;border:none;border-radius:5px;cursor:pointer}
            button:disabled{background:#ccc;cursor:not-allowed}
            #status{margin-top:10px;font-size:0.9em;min-height:24px}
            .edge-preview{margin-top:10px;text-align:center}
            .edge-preview canvas{max-width:100%;max-height:100px;border:1px solid #ddd}
        </style>
    </head>
    <body>
    <div id="scanner">
        <h3>Upload Image → Read Text (Edge Detection)</h3>
        <div id="dropzone" class="dropzone">Drop image or click to upload</div>
        <input type="file" id="fileInput" accept="image/*" style="display:none">
        <div id="preview"></div>
        <div class="edge-preview" id="edgePreview"></div>
        <button id="scanBtn" disabled>Scan Text</button>
        <div id="status"></div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.0/dist/tesseract.min.js"></script>
    <script>
        const dropzone = document.getElementById('dropzone');
        const fileInput = document.getElementById('fileInput');
        const preview = document.getElementById('preview');
        const edgePreview = document.getElementById('edgePreview');
        const scanBtn = document.getElementById('scanBtn');
        const status = document.getElementById('status');

        dropzone.onclick = () => fileInput.click();

        ['dragenter','dragover'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('dragover'); }));
        ['dragleave','drop'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.remove('dragover'); }));

        dropzone.addEventListener('drop', e => {
            const file = e.dataTransfer.files[0];
            if (file && file.type.startsWith('image/')) handleFile(file);
        });

        fileInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (file) handleFile(file);
        });

        function handleFile(file) {
            const url = URL.createObjectURL(file);
            preview.innerHTML = `<img src="${url}" alt="preview">`;
            edgePreview.innerHTML = ''; // Clear edge preview
            scanBtn.disabled = false;
            scanBtn.onclick = () => {
                scanBtn.disabled = true;
                status.innerHTML = 'Applying edge detection...';
                performOCR(file, text => {
                    status.innerHTML = text
                        ? `<span style="color:green"><strong>${text}</strong></span>`
                        : `<span style="color:#d00">No text found</span>`;
                    alert('OCR Result: ' + (text || '(empty)'));
                    scanBtn.disabled = false;
                });
            };
        }

        // Sobel kernels for edge detection
        const sobelX = [
            -1, 0, 1,
            -2, 0, 2,
            -1, 0, 1
        ];
        const sobelY = [
            -1, -2, -1,
             0,  0,  0,
             1,  2,  1
        ];

        async function performOCR(file, callback) {
            const img = new Image();
            img.onload = async () => {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                canvas.width = img.width;
                canvas.height = img.height;
                ctx.drawImage(img, 0, 0);

                // Step 1: Grayscale
                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imgData.data;
                for (let i = 0; i < data.length; i += 4) {
                    const gray = 0.299 * data[i] + 0.587 * data[i + 1] + 0.114 * data[i + 2];
                    data[i] = data[i + 1] = data[i + 2] = gray;
                    data[i + 3] = 255; // Full opacity
                }
                ctx.putImageData(imgData, 0, 0);

                // Step 2: Gaussian blur (simple 3x3 kernel for noise reduction)
                const blurred = applyKernel(canvas, gaussianKernel(3));

                // Step 3: Edge detection (Sobel)
                const edgesX = applyKernel(blurred, sobelX);
                const edgesY = applyKernel(blurred, sobelY);
                const edges = combineMagnitude(edgesX, edgesY);

                // Step 4: Threshold edges to binary (strong edges only)
                const edgeData = edges.getImageData(0, 0, edges.width, edges.height);
                const edgePixels = edgeData.data;
                for (let i = 0; i < edgePixels.length; i += 4) {
                    const mag = edgePixels[i]; // Grayscale magnitude
                    edgePixels[i] = edgePixels[i + 1] = edgePixels[i + 2] = (mag > 50) ? 255 : 0; // Threshold
                    edgePixels[i + 3] = 255;
                }
                edges.putImageData(edgeData, 0, 0);

                // Step 5: Dilate to connect broken edges (simple 3x3 dilation)
                const dilated = dilate(edges);

                // Show edge preview
                const previewCanvas = document.createElement('canvas');
                previewCanvas.width = dilated.width;
                previewCanvas.height = dilated.height;
                previewCanvas.getContext('2d').putImageData(dilated.getImageData(0, 0, dilated.width, dilated.height), 0, 0);
                edgePreview.innerHTML = '<p><strong>Edge-Detected Preview:</strong></p>';
                edgePreview.appendChild(previewCanvas);

                // Upscale for Tesseract
                const scale = 3;
                const up = document.createElement('canvas');
                up.width = dilated.width * scale;
                up.height = dilated.height * scale;
                const uctx = up.getContext('2d');
                uctx.imageSmoothingEnabled = false;
                uctx.drawImage(dilated, 0, 0, up.width, up.height);

                try {
                    const { data: { text } } = await Tesseract.recognize(
                        up.toDataURL('image/png'),
                        'eng',
                        {
                            tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
                            tessedit_pageseg_mode: Tesseract.PSM.SINGLE_WORD // PSM 8: Single word/line
                        }
                    );
                    const cleanText = text.trim().replace(/[^A-Za-z0-9]/g, '').toUpperCase();
                    callback(cleanText);
                } catch (e) {
                    console.error(e);
                    callback('');
                }
            };
            img.src = URL.createObjectURL(file);
        }

        // Gaussian kernel (sigma=1)
        function gaussianKernel(size) {
            const sigma = 1;
            const kernel = [];
            const half = Math.floor(size / 2);
            for (let y = -half; y <= half; y++) {
                for (let x = -half; x <= half; x++) {
                    const val = (1 / (2 * Math.PI * sigma * sigma)) * Math.exp(-(x * x + y * y) / (2 * sigma * sigma));
                    kernel.push(val);
                }
            }
            return kernel;
        }

        // Apply kernel convolution
        function applyKernel(srcCanvas, kernel) {
            const srcCtx = srcCanvas.getContext('2d');
            const srcData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);
            const srcPixels = srcData.data;

            const dstCanvas = document.createElement('canvas');
            dstCanvas.width = srcCanvas.width;
            dstCanvas.height = srcCanvas.height;
            const dstCtx = dstCanvas.getContext('2d');
            const dstData = dstCtx.createImageData(dstCanvas.width, dstCanvas.height);
            const dstPixels = dstData.data;

            const kSize = Math.sqrt(kernel.length);
            const halfK = Math.floor(kSize / 2);

            for (let y = 0; y < srcCanvas.height; y++) {
                for (let x = 0; x < srcCanvas.width; x++) {
                    let sum = 0;
                    let count = 0;
                    for (let ky = -halfK; ky <= halfK; ky++) {
                        for (let kx = -halfK; kx <= halfK; kx++) {
                            const px = x + kx;
                            const py = y + ky;
                            if (px >= 0 && px < srcCanvas.width && py >= 0 && py < srcCanvas.height) {
                                const idx = (py * srcCanvas.width + px) * 4;
                                sum += srcPixels[idx] * kernel[(ky + halfK) * kSize + (kx + halfK)];
                                count++;
                            }
                        }
                    }
                    const val = sum / count;
                    const idx = (y * dstCanvas.width + x) * 4;
                    dstPixels[idx] = dstPixels[idx + 1] = dstPixels[idx + 2] = val;
                    dstPixels[idx + 3] = 255;
                }
            }
            dstCtx.putImageData(dstData, 0, 0);
            return dstCanvas;
        }

        // Combine Sobel X/Y magnitude
        function combineMagnitude(edgesX, edgesY) {
            const xData = edgesX.getContext('2d').getImageData(0, 0, edgesX.width, edgesX.height).data;
            const yData = edgesY.getContext('2d').getImageData(0, 0, edgesY.width, edgesY.height).data;

            const canvas = document.createElement('canvas');
            canvas.width = edgesX.width;
            canvas.height = edgesX.height;
            const ctx = canvas.getContext('2d');
            const data = ctx.createImageData(canvas.width, canvas.height);
            const pixels = data.data;

            for (let i = 0; i < xData.length; i += 4) {
                const mag = Math.sqrt(xData[i] * xData[i] + yData[i] * yData[i]);
                pixels[i] = pixels[i + 1] = pixels[i + 2] = Math.min(255, mag);
                pixels[i + 3] = 255;
            }
            ctx.putImageData(data, 0, 0);
            return canvas;
        }

        // Simple 3x3 dilation
        function dilate(srcCanvas) {
            const srcCtx = srcCanvas.getContext('2d');
            const srcData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);
            const srcPixels = srcData.data;

            const dstCanvas = document.createElement('canvas');
            dstCanvas.width = srcCanvas.width;
            dstCanvas.height = srcCanvas.height;
            const dstCtx = dstCanvas.getContext('2d');
            const dstData = dstCtx.createImageData(dstCanvas.width, dstCanvas.height);
            const dstPixels = dstData.data;

            for (let y = 0; y < srcCanvas.height; y++) {
                for (let x = 0; x < srcCanvas.width; x++) {
                    let max = 0;
                    for (let dy = -1; dy <= 1; dy++) {
                        for (let dx = -1; dx <= 1; dx++) {
                            const px = x + dx;
                            const py = y + dy;
                            if (px >= 0 && px < srcCanvas.width && py >= 0 && py < srcCanvas.height) {
                                const idx = (py * srcCanvas.width + px) * 4;
                                max = Math.max(max, srcPixels[idx]);
                            }
                        }
                    }
                    const idx = (y * dstCanvas.width + x) * 4;
                    dstPixels[idx] = dstPixels[idx + 1] = dstPixels[idx + 2] = max;
                    dstPixels[idx + 3] = 255;
                }
            }
            dstCtx.putImageData(dstData, 0, 0);
            return dstCanvas;
        }
    </script>
    </body>
    </html>
    <?php
    exit;
}
?>

<!-- EMBEDDED MODE -->
<script src="https://cdn.jsdelivr.net/npm/tesseract.js@5.0.0/dist/tesseract.min.js"></script>
<script>
(function(){
    const container = document.getElementById('scanner');
    if (!container) return;

    container.innerHTML = `
        <div id="dropzone" style="border:3px dashed #007bff;padding:25px;margin:10px 0;border-radius:8px;background:#f8fbff;text-align:center;font-size:0.95em;color:#555;cursor:pointer">
            Drop image or click to upload
        </div>
        <input type="file" id="fileInput" accept="image/*" style="display:none">
        <div id="preview" style="text-align:center;margin:10px 0"></div>
        <div class="edge-preview" id="edgePreview" style="margin:10px 0;text-align:center"></div>
        <div style="text-align:center">
            <button id="scanBtn" class="btn btn-primary" disabled>Scan Text</button>
        </div>
        <div id="ocr_status" style="text-align:center;font-size:0.9em;margin-top:8px;min-height:24px"></div>
    `;

    const dropzone = container.querySelector('#dropzone');
    const fileInput = container.querySelector('#fileInput');
    const preview = container.querySelector('#preview');
    const edgePreview = container.querySelector('#edgePreview');
    const scanBtn = container.querySelector('#scanBtn');
    const status = container.querySelector('#ocr_status');

    dropzone.onclick = () => fileInput.click();

    ['dragenter','dragover'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.add('dragover'); }));
    ['dragleave','drop'].forEach(e => dropzone.addEventListener(e, ev => { ev.preventDefault(); dropzone.classList.remove('dragover'); }));

    dropzone.addEventListener('drop', e => {
        const file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) handleFile(file);
    });

    fileInput.addEventListener('change', e => {
        const file = e.target.files[0];
        if (file) handleFile(file);
    });

    function handleFile(file) {
        const url = URL.createObjectURL(file);
        preview.innerHTML = `<img src="${url}" style="max-width:100%;max-height:220px;border-radius:6px;border:1px solid #ddd">`;
        edgePreview.innerHTML = '';
        scanBtn.disabled = false;
        scanBtn.onclick = () => {
            scanBtn.disabled = true;
            status.innerHTML = 'Applying edge detection...';
            performOCR(file, text => {
                status.innerHTML = text
                    ? `<span style="color:green"><strong>${text}</strong></span>`
                    : `<span style="color:#d00">No text found</span>`;

                if (window.parent && window.parent !== window) {
                    window.parent.postMessage({ type: 'PLATE_SCANNED', plate: text }, '*');
                } else {
                    document.getElementById('plate_number')?.setAttribute('value', text);
                }
                scanBtn.disabled = false;
            });
        };
    }

    // Sobel kernels
    const sobelX = [-1,0,1, -2,0,2, -1,0,1];
    const sobelY = [-1,-2,-1, 0,0,0, 1,2,1];

    // Gaussian kernel
    function gaussianKernel(size) {
        const sigma = 1;
        const kernel = [];
        const half = Math.floor(size / 2);
        for (let y = -half; y <= half; y++) {
            for (let x = -half; x <= half; x++) {
                const val = (1 / (2 * Math.PI * sigma * sigma)) * Math.exp(-(x * x + y * y) / (2 * sigma * sigma));
                kernel.push(val);
            }
        }
        return kernel;
    }

    // Apply kernel (same as above)
    function applyKernel(srcCanvas, kernel) {
        const srcCtx = srcCanvas.getContext('2d');
        const srcData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);
        const srcPixels = srcData.data;

        const dstCanvas = document.createElement('canvas');
        dstCanvas.width = srcCanvas.width;
        dstCanvas.height = srcCanvas.height;
        const dstCtx = dstCanvas.getContext('2d');
        const dstData = dstCtx.createImageData(dstCanvas.width, dstCanvas.height);
        const dstPixels = dstData.data;

        const kSize = Math.sqrt(kernel.length);
        const halfK = Math.floor(kSize / 2);

        for (let y = 0; y < srcCanvas.height; y++) {
            for (let x = 0; x < srcCanvas.width; x++) {
                let sum = 0;
                let count = 0;
                for (let ky = -halfK; ky <= halfK; ky++) {
                    for (let kx = -halfK; kx <= halfK; kx++) {
                        const px = x + kx;
                        const py = y + ky;
                        if (px >= 0 && px < srcCanvas.width && py >= 0 && py < srcCanvas.height) {
                            const idx = (py * srcCanvas.width + px) * 4;
                            sum += srcPixels[idx] * kernel[(ky + halfK) * kSize + (kx + halfK)];
                            count++;
                        }
                    }
                }
                const val = sum / count;
                const idx = (y * dstCanvas.width + x) * 4;
                dstPixels[idx] = dstPixels[idx + 1] = dstPixels[idx + 2] = val;
                dstPixels[idx + 3] = 255;
            }
        }
        dstCtx.putImageData(dstData, 0, 0);
        return dstCanvas;
    }

    // Combine magnitude (same as above)
    function combineMagnitude(edgesX, edgesY) {
        const xData = edgesX.getContext('2d').getImageData(0, 0, edgesX.width, edgesX.height).data;
        const yData = edgesY.getContext('2d').getImageData(0, 0, edgesY.width, edgesY.height).data;

        const canvas = document.createElement('canvas');
        canvas.width = edgesX.width;
        canvas.height = edgesX.height;
        const ctx = canvas.getContext('2d');
        const data = ctx.createImageData(canvas.width, canvas.height);
        const pixels = data.data;

        for (let i = 0; i < xData.length; i += 4) {
            const mag = Math.sqrt(xData[i] * xData[i] + yData[i] * yData[i]);
            pixels[i] = pixels[i + 1] = pixels[i + 2] = Math.min(255, mag);
            pixels[i + 3] = 255;
        }
        ctx.putImageData(data, 0, 0);
        return canvas;
    }

    // Dilate (same as above)
    function dilate(srcCanvas) {
        const srcCtx = srcCanvas.getContext('2d');
        const srcData = srcCtx.getImageData(0, 0, srcCanvas.width, srcCanvas.height);
        const srcPixels = srcData.data;

        const dstCanvas = document.createElement('canvas');
        dstCanvas.width = srcCanvas.width;
        dstCanvas.height = srcCanvas.height;
        const dstCtx = dstCanvas.getContext('2d');
        const dstData = dstCtx.createImageData(dstCanvas.width, dstCanvas.height);
        const dstPixels = dstData.data;

        for (let y = 0; y < srcCanvas.height; y++) {
            for (let x = 0; x < srcCanvas.width; x++) {
                let max = 0;
                for (let dy = -1; dy <= 1; dy++) {
                    for (let dx = -1; dx <= 1; dx++) {
                        const px = x + dx;
                        const py = y + dy;
                        if (px >= 0 && px < srcCanvas.width && py >= 0 && py < srcCanvas.height) {
                            const idx = (py * srcCanvas.width + px) * 4;
                            max = Math.max(max, srcPixels[idx]);
                        }
                    }
                }
                const idx = (y * dstCanvas.width + x) * 4;
                dstPixels[idx] = dstPixels[idx + 1] = dstPixels[idx + 2] = max;
                dstPixels[idx + 3] = 255;
            }
        }
        dstCtx.putImageData(dstData, 0, 0);
        return dstCanvas;
    }

    // performOCR function (updated with edge detection, same as standalone)
    async function performOCR(file, callback) {
        // ... (copy the performOCR from standalone version above)
        // It includes grayscale, Gaussian blur, Sobel edges, threshold, dilation, upscale, Tesseract
    }
})();
</script>