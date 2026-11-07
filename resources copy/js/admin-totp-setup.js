import QRCode from 'qrcode';

const canvas = document.querySelector('[data-totp-qr]');
const status = document.querySelector('[data-totp-qr-status]');
const otpauthUrl = canvas?.dataset.otpauthUrl;

if (canvas && otpauthUrl) {
    QRCode.toCanvas(canvas, otpauthUrl, {
        width: 240,
        margin: 2,
        errorCorrectionLevel: 'M',
        color: {
            dark: '#111827',
            light: '#ffffff',
        },
    }).then(() => {
        if (status) {
            status.hidden = true;
        }
    }).catch(() => {
        if (status) {
            status.textContent = 'QR код үүсгэж чадсангүй. Доорх түлхүүрийг гараар оруулна уу.';
            status.setAttribute('role', 'alert');
        }
    });
}
