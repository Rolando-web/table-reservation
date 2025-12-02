// admin-dashboard.js
// This file was extracted from admin_dashboard.php. It provides UI helpers and PDF export.

// load html2pdf library dynamically (CDN)
(function(){
    var s = document.createElement('script');
    s.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js';
    s.async = true;
    document.head.appendChild(s);
})();

function generateReportPDF() {
    const content = document.getElementById('reportContent');
    if (!content) return alert('Report content not found');

    // clone and make visible (but invisible to the user) for rendering
    const clone = content.cloneNode(true);
    clone.style.display = 'block';
   
    clone.style.position = 'fixed';
    clone.style.left = '0';
    clone.style.top = '0';
    clone.style.width = '800px';
    clone.style.zIndex = '99999';
    clone.style.background = '#ffffff';
    clone.style.opacity = '0';
    clone.style.pointerEvents = 'none';
    document.body.appendChild(clone);

    const opt = {
        margin:       10,
        filename:     'analytics-report-'+(new Date()).toISOString().slice(0,10)+'.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, backgroundColor: '#ffffff', logging: false },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };

    // wait for html2pdf to be available
    const waitForLib = () => {
        if (window.html2pdf) {
            try {
                html2pdf().set(opt).from(clone).save().then(()=>{
                    setTimeout(() => document.body.removeChild(clone), 100);
                }).catch((err)=>{
                    document.body.removeChild(clone);
                    console.error(err);
                    alert('Failed to generate PDF: ' + err.message);
                });
            } catch (e) {
                document.body.removeChild(clone);
                console.error(e);
                alert('Failed to generate PDF: ' + e.message);
            }
        } else {
            setTimeout(waitForLib, 200);
        }
    };
    waitForLib();
}

function showTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.tab-button').forEach(el => {
        el.classList.remove('border-amber-500', 'text-amber-600');
        el.classList.add('border-transparent', 'text-gray-500');
    });
    
    document.getElementById(tab + 'Content').classList.remove('hidden');
    document.getElementById(tab + 'Tab').classList.remove('border-transparent', 'text-gray-500');
    document.getElementById(tab + 'Tab').classList.add('border-amber-500', 'text-amber-600');
}

function openAddTableModal() {
    document.getElementById('addTableModal').classList.remove('hidden');
}

function closeAddTableModal() {
    document.getElementById('addTableModal').classList.add('hidden');
}

function openEditTableModal(tableJson) {
    try {
        let table;
        if (typeof tableJson === 'string') {
            try {
                const decoded = decodeURIComponent(tableJson);
                table = JSON.parse(decoded);
            } catch (e) {
                // Fallback: maybe not encoded
                table = JSON.parse(tableJson);
            }
        } else {
            table = tableJson;
        }
        document.getElementById('edit_table_id').value = table.id;
        document.getElementById('edit_table_number').value = table.table_number;
        document.getElementById('edit_capacity').value = table.capacity;
        document.getElementById('edit_location').value = table.location;
        document.getElementById('edit_price').value = table.price || 500.00;
        // show current image preview if available
        const preview = document.getElementById('editImagePreview');
        if (table.image_url) {
            preview.src = table.image_url;
            preview.classList.remove('hidden');
        } else {
            preview.classList.add('hidden');
        }
        document.getElementById('editTableModal').classList.remove('hidden');
    } catch (e) {
        alert('Failed to open edit modal');
    }
}

function closeEditTableModal() {
    document.getElementById('editTableModal').classList.add('hidden');
}

// Open Edit User Modal and populate fields
function openEditUserModal(user) {
    document.getElementById('edit_user_id').value = user.id;
    document.getElementById('edit_user_username').value = user.username;
    document.getElementById('edit_user_email').value = user.email;
    document.getElementById('edit_user_phone').value = user.phone || '';
    document.getElementById('edit_user_password').value = ''; // Clear password field
    document.getElementById('editUserModal').classList.remove('hidden');
}

function closeEditUserModal() {
    document.getElementById('editUserModal').classList.add('hidden');
}
