    <!-- Reservations Tab -->
        <div id="reservationsContent" class="tab-content">
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-xl font-bold text-gray-800">All Reservations</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Table</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Guests</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($reservation = $reservations->fetch_assoc()): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900"><?php echo htmlspecialchars($reservation['username']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['email']); ?></div>
                                        <?php if ($reservation['phone']): ?>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['phone']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-semibold text-gray-900">Table <?php echo htmlspecialchars($reservation['table_number']); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['location']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-gray-900"><?php echo date('M d, Y', strtotime($reservation['reservation_date'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('h:i A', strtotime($reservation['reservation_time'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-gray-900">
                                        <i class="fas fa-users mr-1"></i><?php echo $reservation['guests']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" 
                                                    class="text-xs font-semibold rounded-full px-3 py-1 border-0 focus:ring-2 focus:ring-amber-500
                                                    <?php 
                                                    echo $reservation['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                                        ($reservation['status'] == 'confirmed' ? 'bg-green-100 text-green-800' : 
                                                        ($reservation['status'] == 'cancelled' ? 'bg-red-100 text-red-800' : 
                                                        'bg-blue-100 text-blue-800')); 
                                                    ?>">
                                                <option value="pending" <?php echo $reservation['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                <option value="confirmed" <?php echo $reservation['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                <option value="completed" <?php echo $reservation['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                <option value="cancelled" <?php echo $reservation['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                <option value="rejected" <?php echo $reservation['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            </select>
                                            <input type="hidden" name="update_status" value="1">
                                        </form>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center space-x-2">
                                            <?php if ($reservation['status'] == 'pending'): ?>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" name="approve_reservation" value="1"
                                                            class="bg-green-500 hover:bg-green-600 text-white px-3 py-1 rounded text-xs font-semibold transition"
                                                            onclick="return confirm('Approve this reservation?')">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                </form>
                                                <form method="POST" class="inline">
                                                    <input type="hidden" name="reservation_id" value="<?php echo $reservation['id']; ?>">
                                                    <button type="submit" name="reject_reservation" value="1"
                                                            class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-xs font-semibold transition"
                                                            onclick="return confirm('Reject this reservation?')">
                                                        <i class="fas fa-times mr-1"></i>Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <?php if ($reservation['special_requests']): ?>
                                                <button onclick="alert('Special Requests: <?php echo addslashes($reservation['special_requests']); ?>')" 
                                                        class="text-amber-600 hover:text-amber-900">
                                                    <i class="fas fa-comment-dots"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>