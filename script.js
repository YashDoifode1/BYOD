// assets/js/device-fingerprint.js
document.addEventListener('DOMContentLoaded', function() {
    const fingerprintData = {
        screen: {
            width: screen.width,
            height: screen.height,
            colorDepth: screen.colorDepth,
            pixelRatio: window.devicePixelRatio || 1
        },
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        plugins: Array.from(navigator.plugins || []).map(p => p.name),
        fonts: (() => {
            try {
                return Array.from(document.fonts).map(f => f.family);
            } catch (e) {
                return [];
            }
        })(),
        hardware: {
            concurrency: navigator.hardwareConcurrency || 'unknown',
            memory: navigator.deviceMemory || 'unknown'
        },
        webgl: (() => {
            try {
                const canvas = document.createElement('canvas');
                const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                if (!gl) return null;
                return {
                    vendor: gl.getParameter(gl.VENDOR),
                    renderer: gl.getParameter(gl.RENDERER)
                };
            } catch (e) {
                return null;
            }
        })()
    };

    // Store in hidden form field
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'client_device_data';
    input.value = JSON.stringify(fingerprintData);
    document.forms[0].appendChild(input);
});