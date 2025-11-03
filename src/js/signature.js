const canvas = document.getElementById('signatureCanvas');
const ctx = canvas ? canvas.getContext('2d') : null;
let isDrawing = false;
let lastX = 0;
let lastY = 0;

if (canvas) {
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    // Touch support
    canvas.addEventListener('touchstart', handleTouch);
    canvas.addEventListener('touchmove', handleTouch);
    canvas.addEventListener('touchend', stopDrawing);
}

function startDrawing(e) {
    isDrawing = true;
    const rect = canvas.getBoundingClientRect();
    lastX = e.clientX - rect.left;
    lastY = e.clientY - rect.top;
}

function draw(e) {
    if (!isDrawing) return;

    const rect = canvas.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;

    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    ctx.beginPath();
    ctx.moveTo(lastX, lastY);
    ctx.lineTo(x, y);
    ctx.stroke();

    lastX = x;
    lastY = y;

    document.getElementById('signature_data').value = canvas.toDataURL('image/png');
}

function handleTouch(e) {
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent(e.type === 'touchstart' ? 'mousedown' : 'mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    canvas.dispatchEvent(mouseEvent);
}

function stopDrawing() {
    isDrawing = false;
}

function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById('signature_data').value = '';
}

function formatIban(input) {
    let value = input.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();

    let formatted = '';
    for (let i = 0; i < value.length; i++) {
        if (i > 0 && i % 4 === 0) {
            formatted += ' ';
        }
        formatted += value[i];
    }

    input.value = formatted;
}

function toggleDirectDebit() {
    const directDebit = document.querySelector('input[name="payment_method"][value="direct_debit"]').checked;
    const fields = document.getElementById('directDebitFields');

    if (directDebit) {
        fields.classList.add('active');
        // Make fields required
        document.getElementById('iban').required = true;
        document.getElementById('account_holder_name').required = true;
        document.getElementById('mandate_date').required = true;
    } else {
        fields.classList.remove('active');
        // Make fields optional
        document.getElementById('iban').required = false;
        document.getElementById('account_holder_name').required = false;
        document.getElementById('mandate_date').required = false;
    }
}

toggleDirectDebit();

document.querySelector('form').addEventListener('submit', function (e) {
    const directDebit = document.querySelector('input[name="payment_method"][value="direct_debit"]').checked;
    if (directDebit) {
        const signatureData = document.getElementById('signature_data').value;
        if (!signatureData) {
            e.preventDefault();
            alert('Handtekening is verplicht voor automatisch incasso. Teken uw handtekening alstublieft.');
        }
    }
});