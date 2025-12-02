 <!-- Add Table Modal -->
    <div id="addTableModal" class="hidden fixed z-50 inset-0 overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>

            <div class="inline-block align-bottom bg-white rounded-2xl text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-2xl font-bold text-gray-900">Add New Table</h3>
                            <button type="button" onclick="closeAddTableModal()" class="text-gray-400 hover:text-gray-600">
                                <i class="fas fa-times text-2xl"></i>
                            </button>
                        </div>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Table Number</label>
                                <input type="text" name="table_number" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="e.g., T9">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Capacity (Seats)</label>
                                <input type="number" name="capacity" min="1" required 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Location</label>
                                <select name="location" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent">
                                    <option value="Window Side">Window Side</option>
                                    <option value="Corner">Corner</option>
                                    <option value="Center">Center</option>
                                    <option value="Outdoor">Outdoor</option>
                                    <option value="Patio">Patio</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Base Price (₱)</label>
                                <input type="number" name="price" min="0" step="0.01" value="500" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="500">
                                <p class="text-xs text-gray-500 mt-1">Base reservation price for this table (default: ₱500)</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Image URL</label>
                                <input type="file" name="image_file" accept="image/*" class="w-full" />
                                <p class="text-xs text-gray-500 mt-1">Upload an image file (recommended) or leave blank and provide a URL below.</p>
                                <input type="url" name="image_url" 
                                       class="w-full mt-2 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-amber-500 focus:border-transparent"
                                       placeholder="Or provide an external image URL (optional)">
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" name="add_table" value="1"
                                class="w-full inline-flex justify-center rounded-lg border border-transparent shadow-sm px-4 py-2 bg-amber-600 text-base font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:ml-3 sm:w-auto sm:text-sm">
                            <i class="fa-plus mr-2"></i>Add Table
                        </button>
                        <button type="button" onclick="closeAddTableModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-lg border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>