<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Icon Generator - FoC Connect</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #0F4C3A 0%, #2E7D64 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 40px;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
        }
        h1 {
            font-size: 32px;
            color: #0F4C3A;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .subtitle {
            color: #6B7E78;
            margin-bottom: 32px;
            font-size: 14px;
            border-bottom: 1px solid #E8EDEC;
            padding-bottom: 16px;
        }
        .info-box {
            background: #E8F5E9;
            border-left: 4px solid #2E7D64;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .info-box h3 {
            color: #0F4C3A;
            margin-bottom: 8px;
            font-size: 16px;
        }
        .info-box p {
            color: #4A5568;
            font-size: 14px;
            line-height: 1.5;
        }
        .btn-generate {
            background: #2E7D64;
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 48px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            margin-bottom: 24px;
            transition: all 0.2s;
        }
        .btn-generate:hover {
            background: #236753;
            transform: scale(0.98);
        }
        .progress {
            background: #F5F7F6;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: none;
        }
        .progress.show {
            display: block;
        }
        .progress-bar {
            background: #E8EDEC;
            border-radius: 40px;
            height: 8px;
            overflow: hidden;
            margin-top: 12px;
        }
        .progress-fill {
            background: #2E7D64;
            height: 100%;
            width: 0%;
            transition: width 0.3s;
            border-radius: 40px;
        }
        .icon-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
            max-height: 400px;
            overflow-y: auto;
            padding: 8px;
        }
        .icon-item {
            text-align: center;
            padding: 12px;
            background: #F5F7F6;
            border-radius: 16px;
            border: 1px solid #E8EDEC;
        }
        .icon-item canvas {
            border-radius: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .icon-item p {
            margin-top: 8px;
            font-size: 11px;
            color: #6B7E78;
        }
        .success-box {
            background: #E8F5E9;
            border-radius: 16px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }
        .success-box.show {
            display: block;
        }
        .manifest-box {
            background: #1A2E28;
            color: #E8F5E9;
            padding: 16px;
            border-radius: 12px;
            font-family: monospace;
            font-size: 12px;
            overflow-x: auto;
            margin-top: 16px;
        }
        .copy-btn {
            background: #2E7D64;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 12px;
        }
        .folder-instruction {
            background: #FFF8E7;
            border-left: 4px solid #D69E2E;
            padding: 16px;
            border-radius: 12px;
            margin-top: 20px;
        }
        code {
            background: #F0F0F0;
            padding: 2px 8px;
            border-radius: 8px;
            font-family: monospace;
            font-size: 13px;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #E8EDEC;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>
        <span style="font-size: 40px;">🌿</span>
        FoC Connect Icon Generator
    </h1>
    <div class="subtitle">Generate app icons for PWA installation</div>

    <div class="info-box">
        <h3>📱 What this does</h3>
        <p>This tool generates all the icons needed to make FoC Connect installable as a mobile app on Android and iOS devices. The icons will be created in your browser and downloaded automatically.</p>
    </div>

    <button class="btn-generate" id="generateBtn" onclick="generateIcons()">
        🎨 Generate All Icons
    </button>

    <div class="progress" id="progress">
        <div>Generating icons... <span id="progressCount">0</span> / <span id="totalCount">0</span></div>
        <div class="progress-bar">
            <div class="progress-fill" id="progressFill"></div>
        </div>
    </div>

    <div id="iconGrid" class="icon-grid"></div>

    <div class="success-box" id="successBox">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
            <span style="font-size: 32px;">✅</span>
            <strong style="color: #0F4C3A; font-size: 18px;">All icons generated successfully!</strong>
        </div>

        <div class="folder-instruction">
            <strong>📁 Step 1: Create the icons folder</strong><br>
            Create this folder on your computer:<br>
            <code>C:\xampp\htdocs\foc-connect\assets\icons\</code><br>
            <br>
            <strong>📥 Step 2: Save the downloaded icons</strong><br>
            Move all the downloaded PNG files to the folder above.
        </div>

        <hr>

        <strong>📋 Step 3: Copy this to your manifest.json file</strong>
        <div class="manifest-box" id="manifestContent"></div>
        <button class="copy-btn" onclick="copyManifest()">📋 Copy to Clipboard</button>

        <hr>

        <strong>🚀 Step 4: Test the PWA</strong><br>
        <ol style="margin-left: 20px; margin-top: 10px; color: #4A5568;">
            <li>Open Chrome on your phone</li>
            <li>Go to <code>http://localhost/foc-connect/dashboard.php</code></li>
            <li>Look for the "Add to Home Screen" prompt</li>
            <li>Tap "Install"</li>
        </ol>
    </div>
</div>

<script>
    const sizes = [72, 96, 128, 144, 152, 192, 384, 512];
    const bgColor = '#0F4C3A';
    const accentColor = '#D69E2E';
    
    let iconsGenerated = 0;
    let manifestIcons = [];

    function generateIcons() {
        // Reset
        iconsGenerated = 0;
        manifestIcons = [];
        document.getElementById('iconGrid').innerHTML = '';
        document.getElementById('progress').classList.add('show');
        document.getElementById('totalCount').innerText = sizes.length;
        document.getElementById('progressCount').innerText = '0';
        document.getElementById('progressFill').style.width = '0%';
        document.getElementById('successBox').classList.remove('show');
        
        // Generate each icon
        sizes.forEach((size, index) => {
            setTimeout(() => {
                generateIcon(size, index);
            }, index * 200);
        });
    }
    
    function generateIcon(size, index) {
        // Create canvas
        const canvas = document.createElement('canvas');
        canvas.width = size;
        canvas.height = size;
        const ctx = canvas.getContext('2d');
        
        // Draw background
        ctx.fillStyle = bgColor;
        ctx.fillRect(0, 0, size, size);
        
        // Draw gradient border
        const gradient = ctx.createLinearGradient(0, 0, size, size);
        gradient.addColorStop(0, accentColor);
        gradient.addColorStop(1, '#B7791F');
        ctx.strokeStyle = gradient;
        ctx.lineWidth = Math.max(2, size / 50);
        ctx.strokeRect(3, 3, size - 6, size - 6);
        
        // Draw inner circle highlight
        ctx.beginPath();
        ctx.arc(size/2, size/2, size/2.5, 0, Math.PI * 2);
        ctx.fillStyle = 'rgba(255,255,255,0.05)';
        ctx.fill();
        
        // Draw leaf icon (🌿)
        ctx.font = `${Math.floor(size / 2.2)}px "Segoe UI Emoji", "Apple Color Emoji", "Noto Color Emoji", sans-serif`;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#FFFFFF';
        ctx.fillText("🌿", size / 2, size / 2 - 5);
        
        // Draw "FoC" text
        ctx.font = `bold ${Math.floor(size / 8)}px "Segoe UI", Arial, sans-serif`;
        ctx.fillStyle = 'rgba(255,255,255,0.9)';
        ctx.fillText("FoC CONNECT", size / 2, size - Math.floor(size / 6.5));
        
        // Draw connection dots (Wi-Fi style)
        const dotColors = ['#2E7D64', '#3A997A', '#4AB586'];
        for(let i = 1; i <= 3; i++) {
            const dotSize = Math.max(3, Math.floor(size / 45));
            const dotX = size - (i * dotSize * 3.5) - dotSize;
            const dotY = dotSize * 2.5;
            ctx.fillStyle = dotColors[i-1];
            ctx.beginPath();
            ctx.arc(dotX, dotY, dotSize, 0, 2 * Math.PI);
            ctx.fill();
        }
        
        // Draw small decorative leaves
        if(size >= 128) {
            ctx.fillStyle = 'rgba(46,125,100,0.5)';
            for(let i = 1; i <= 3; i++) {
                const leafSize = Math.max(3, Math.floor(size / 30));
                const leafX = size - (i * leafSize * 4);
                const leafY = size - leafSize * 3;
                ctx.beginPath();
                ctx.arc(leafX, leafY, leafSize, 0, 2 * Math.PI);
                ctx.fill();
            }
        }
        
        // Add icon to preview grid
        const iconGrid = document.getElementById('iconGrid');
        const iconDiv = document.createElement('div');
        iconDiv.className = 'icon-item';
        iconDiv.innerHTML = `
            <canvas width="${size}" height="${size}" style="width: 64px; height: 64px;"></canvas>
            <p>${size}x${size}</p>
        `;
        const previewCanvas = iconDiv.querySelector('canvas');
        const previewCtx = previewCanvas.getContext('2d');
        previewCtx.drawImage(canvas, 0, 0, size, size, 0, 0, 64, 64);
        iconGrid.appendChild(iconDiv);
        
        // Download the icon
        const link = document.createElement('a');
        link.download = `icon-${size}x${size}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
        
        // Add to manifest
        manifestIcons.push({
            src: `assets/icons/icon-${size}x${size}.png`,
            sizes: `${size}x${size}`,
            type: "image/png",
            purpose: "any maskable"
        });
        
        // Update progress
        iconsGenerated++;
        document.getElementById('progressCount').innerText = iconsGenerated;
        document.getElementById('progressFill').style.width = `${(iconsGenerated / sizes.length) * 100}%`;
        
        // Check if all done
        if(iconsGenerated === sizes.length) {
            setTimeout(() => {
                showSuccess();
            }, 500);
        }
    }
    
    function showSuccess() {
        document.getElementById('progress').classList.remove('show');
        document.getElementById('successBox').classList.add('show');
        
        // Create manifest JSON
        const manifest = {
            name: "FoC Connect",
            short_name: "FoC",
            description: "Faculty of Computing Communication Platform",
            start_url: "/foc-connect/dashboard.php",
            display: "standalone",
            theme_color: "#0F4C3A",
            background_color: "#F5F7F6",
            orientation: "portrait",
            icons: manifestIcons
        };
        
        document.getElementById('manifestContent').innerHTML = JSON.stringify(manifest, null, 2);
    }
    
    function copyManifest() {
        const text = document.getElementById('manifestContent').innerText;
        navigator.clipboard.writeText(text).then(() => {
            alert('✅ Manifest JSON copied to clipboard!');
        });
    }
</script>
</body>
</html>