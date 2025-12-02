// Table filtering and search
let currentLocationFilter = '';

function filterTables() {
  const searchTerm = document.getElementById('searchTable').value.toLowerCase();
  const capacityFilter = document.getElementById('capacityFilter').value;
  const tables = document.querySelectorAll('.table-card');
  let visibleCount = 0;

  tables.forEach(table => {
    const tableNumber = table.dataset.tableNumber.toLowerCase();
    const location = table.dataset.location.toLowerCase();
    const capacity = parseInt(table.dataset.capacity);

    let matchesSearch = tableNumber.includes(searchTerm) || location.includes(searchTerm);
    let matchesCapacity = !capacityFilter || (capacityFilter === '8' ? capacity >= 8 : capacity == capacityFilter);
    let matchesLocation = !currentLocationFilter || location.includes(currentLocationFilter.toLowerCase());

    if (matchesSearch && matchesCapacity && matchesLocation) {
      table.style.display = 'block';
      visibleCount++;
    } else {
      table.style.display = 'none';
    }
  });

  const noResults = document.getElementById('noResults');
  const tablesGrid = document.getElementById('tablesGrid');
  const tableCount = document.getElementById('tableCount');

  if (visibleCount === 0) {
    noResults.classList.remove('hidden');
    tablesGrid.style.display = 'none';
  } else {
    noResults.classList.add('hidden');
    tablesGrid.style.display = 'grid';
  }

  tableCount.textContent = `Showing ${visibleCount} table${visibleCount !== 1 ? 's' : ''}`;
}

function filterByLocation(location) {
  currentLocationFilter = location;
  document.querySelectorAll('.location-filter').forEach(btn => {
    if (btn.dataset.location === location) {
      btn.classList.add('filter-active');
      btn.classList.remove('border-gray-300');
    } else {
      btn.classList.remove('filter-active');
      btn.classList.add('border-gray-300');
    }
  });
  filterTables();
}

function resetFilters() {
  document.getElementById('searchTable').value = '';
  document.getElementById('capacityFilter').value = '';
  currentLocationFilter = '';
  document.querySelectorAll('.location-filter').forEach(btn => {
    if (btn.dataset.location === '') {
      btn.classList.add('filter-active');
      btn.classList.remove('border-gray-300');
    } else {
      btn.classList.remove('filter-active');
      btn.classList.add('border-gray-300');
    }
  });
  filterTables();
}

// Reservation filtering
function filterReservations(status) {
  const rows = document.querySelectorAll('.reservation-row');
  document.querySelectorAll('.reservation-filter').forEach(btn => {
    if (btn.dataset.status === status) {
      btn.classList.add('filter-active');
    } else {
      btn.classList.remove('filter-active');
    }
  });
  rows.forEach(row => {
    if (!status || row.dataset.status === status) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

// Reservation search
function searchReservations() {
  const searchTerm = document.getElementById('searchReservation').value.toLowerCase();
  const rows = document.querySelectorAll('.reservation-row');
  rows.forEach(row => {
    const table = row.dataset.table.toLowerCase();
    const location = row.dataset.location.toLowerCase();
    const date = row.dataset.date.toLowerCase();
    if (table.includes(searchTerm) || location.includes(searchTerm) || date.includes(searchTerm)) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

let currentTablePrice = 500;

function openBookingModal(tableId, tableNumber, capacity, price = 500) {
  currentTablePrice = price;
  document.getElementById('bookingModal').classList.remove('hidden');
  document.getElementById('modalTableId').value = tableId;
  document.getElementById('modalTableName').textContent = 'Book Table ' + tableNumber;
  document.getElementById('modalGuests').max = capacity;
  document.getElementById('modalGuests').value = capacity;
  document.getElementById('maxCapacity').textContent = capacity;
  
  // Add event listeners for occupancy checking
  const dateInput = document.querySelector('input[name="reservation_date"]');
  const timeInput = document.querySelector('input[name="reservation_time"]');
  
  const checkOccupancy = () => {
    const date = dateInput.value;
    const time = timeInput.value;
    
    if (date && time) {
      fetch(`check_occupancy.php?table_id=${tableId}&date=${date}&time=${time}`)
        .then(response => response.json())
        .then(data => {
          const submitBtn = document.querySelector('button[name="book_table"]');
          const modalContent = document.querySelector('#bookingModal form > div:first-child');
          
          // Remove any existing warning
          const existingWarning = document.getElementById('occupancyWarning');
          if (existingWarning) existingWarning.remove();
          
          if (data.occupied) {
            // Show warning and disable button
            const warning = document.createElement('div');
            warning.id = 'occupancyWarning';
            warning.className = 'bg-red-50 border border-red-200 rounded-lg p-3 mt-4';
            warning.innerHTML = `
              <div class="flex items-center">
                <i class="fas fa-exclamation-circle text-red-500 mr-2"></i>
                <span class="text-sm text-red-700 font-medium">${data.message}</span>
              </div>
            `;
            modalContent.appendChild(warning);
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            submitBtn.innerHTML = '<i class="fas fa-ban mr-2"></i>Table Occupied';
          } else {
            submitBtn.disabled = false;
            submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            submitBtn.innerHTML = '<i class="fas fa-check mr-2"></i>Confirm Booking';
          }
        })
        .catch(err => console.error('Error checking occupancy:', err));
    }
  };
  
  dateInput.addEventListener('change', checkOccupancy);
  timeInput.addEventListener('change', checkOccupancy);
}

function closeBookingModal() {
  document.getElementById('bookingModal').classList.add('hidden');
}

function openPaymentModal(reservationId, amount) {
  document.getElementById('paymentModal').classList.remove('hidden');
  document.getElementById('paymentReservationId').value = reservationId;
  document.getElementById('paymentAmount').value = amount;
  document.getElementById('displayAmount').textContent = '₱' + parseFloat(amount).toFixed(2);
  // Respect previously selected payment method if present, otherwise default to GCash
  let current = document.getElementById('paymentMethod').value || 'GCash';
  if (current !== 'GCash' && current !== 'Cash') current = 'GCash';
  selectPaymentMethod(current);
}

function closePaymentModal() {
  document.getElementById('paymentModal').classList.add('hidden');
}

function selectPaymentMethod(method) {
  try {
    console.log('selectPaymentMethod called with:', method);
    document.getElementById('paymentMethod').value = method;
  } catch (err) {
    console.error('selectPaymentMethod error setting method:', err);
  }
  const btnText = document.getElementById('paymentBtnText');
  btnText.textContent = method === 'GCash' ? 'Pay with GCash' : 'Pay Now';
  // Find all method buttons by data-method attribute
  const methodButtons = document.querySelectorAll('.payment-method-btn');

  try {
    methodButtons.forEach(btn => {
    // normalize
    btn.classList.remove('active','border-amber-500','bg-amber-50','border-blue-500','bg-blue-50','border-green-500','bg-green-50');
    btn.classList.remove('border-gray-300','bg-white');
    btn.classList.add('border-gray-300','bg-white');
  });
  } catch (err) { console.error('error normalizing buttons', err); }

  // find the matching button
  const targetBtn = Array.from(methodButtons).find(b => (b.dataset.method || b.getAttribute('data-method')) === method);
  if (targetBtn) {
    // apply styles based on method
    if (method === 'GCash') {
      targetBtn.classList.add('active','border-blue-500','bg-blue-50');
    } else if (method === 'Cash') {
      targetBtn.classList.add('active','border-green-500','bg-green-50');
    } else {
      targetBtn.classList.add('active');
    }
  }
  else {
    console.warn('selectPaymentMethod: no matching button found for method:', method, 'available:', Array.from(methodButtons).map(b=>b.dataset.method||b.getAttribute('data-method')));
  }

  // Toggle fields and required attributes
  const showGcash = method === 'GCash';
  const showCash = method === 'Cash';

  const gcashFields = document.getElementById('gcashFields');
  const cashFields = document.getElementById('cashFields');
  if (gcashFields) gcashFields.classList.toggle('hidden', !showGcash);
  if (cashFields) cashFields.classList.toggle('hidden', !showCash);

  const gcashNumber = document.getElementById('gcashNumber');
  const gcashName = document.getElementById('gcashName');
  if (gcashNumber) gcashNumber.required = showGcash;
  if (gcashName) gcashName.required = showGcash;

  btnText.textContent = showGcash ? 'Pay with GCash' : (showCash ? 'Confirm Cash Payment' : 'Pay Now');
}

function formatPhoneNumber(input) {
  let value = input.value.replace(/\D/g, '');
  if (value.length > 10) value = value.substring(0, 10);
  if (value.length > 6) {
    value = value.substring(0, 3) + ' ' + value.substring(3, 6) + ' ' + value.substring(6);
  } else if (value.length > 3) {
    value = value.substring(0, 3) + ' ' + value.substring(3);
  }
  input.value = value;
}

function toggleNotifications() {
  const dropdown = document.getElementById('notificationDropdown');
  dropdown.classList.toggle('hidden');
}

document.addEventListener('click', function(event) {
  const dropdown = document.getElementById('notificationDropdown');
  const bell = event.target.closest('.fa-bell');
  if (!dropdown.contains(event.target) && !bell) {
    dropdown.classList.add('hidden');
  }
});

window.addEventListener('load', function() {
  document.querySelectorAll('.table-card').forEach((card, index) => {
    setTimeout(() => {
      card.style.opacity = '0';
      card.style.animation = `fadeIn 0.5s ease-out ${index * 0.05}s forwards`;
    }, 0);
  });
  // Attach click handlers to payment method buttons (defensive: in case inline onclick isn't working)
  const gcashBtn = document.getElementById('btnGCash');
  const cashBtn = document.getElementById('btnCash');
  // instead of individual listeners, attach to all .payment-method-btn to ensure dataset.method is used
  document.querySelectorAll('.payment-method-btn').forEach(b => {
    b.addEventListener('click', (e) => {
      const m = b.dataset.method || b.getAttribute('data-method');
      if (m) selectPaymentMethod(m);
    });
  });
  // Extra defensive delegation on the payment method container (covers dynamic/modal cases)
  const paymentContainer = document.querySelector('#paymentModal .grid');
  if (paymentContainer) {
    paymentContainer.addEventListener('click', (e) => {
      const btn = e.target.closest('.payment-method-btn');
      if (!btn) return;
      // Ensure pointer events and focusability
      btn.style.pointerEvents = 'auto';
      btn.setAttribute('aria-pressed', 'true');
      const method = btn.dataset.method || btn.getAttribute('data-method');
      if (method) {
        e.preventDefault();
        e.stopPropagation();
        selectPaymentMethod(method);
        // ensure the hidden input is set immediately
        const hidden = document.getElementById('paymentMethod');
        if (hidden) hidden.value = method;
      }
    });
  }
});
