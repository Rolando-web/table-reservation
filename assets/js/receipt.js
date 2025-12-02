function generatePDF() {
  const element = document.getElementById('receipt');
  const opt = {
    margin: 0.5,
    // filename will be set server-side in PHP when embedded; fallback provided
    filename: 'coffee-table-receipt.pdf',
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { scale: 2 },
    jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
  };
  html2pdf().set(opt).from(element).save();
}
