(function($){
  // Debounce helper
  function debounce(fn, delay) {
    let t;
    return function(...args) {
      clearTimeout(t);
      t = setTimeout(() => fn.apply(this, args), delay);
    };
  }

  // DOM refs expected on page:
  // #product_search (input), #product_suggestions (div)
  // #productDetails (tbody) is your purchase table’s body
  // #addProductModal, #addProductForm for add-new
  // #batchTableModal with #batchTable for batch selection

  const $search = $('#product_search');
  const $suggestions = $('#product_suggestions');

  // Typeahead search
  $search.on('input', debounce(function() {
    const q = $search.val().trim();
    if (!q) { $suggestions.hide().empty(); return; }

    $.getJSON('../ajax/search_product.php', { q }, function(resp) {
      $suggestions.empty();
      if (!resp || !resp.length) {
        $suggestions
          .show()
          .append('<div class="list-group-item no-results d-flex justify-content-between align-items-center">No results <button type="button" class="btn btn-sm btn-outline-primary" id="suggestAddProduct">Add product</button></div>');
        return;
      }
      resp.forEach(row => {
        const item = $(
          `<a class="list-group-item list-group-item-action" data-id="${row.product_id}" data-name="${row.product_name}">
            ${row.product_name}
          </a>`
        );
        $suggestions.append(item);
      });
      $suggestions.show();
    }).fail(()=> {
      $suggestions.hide().empty();
    });
  }, 250));

  // Click suggestions
  $suggestions.on('click', '.list-group-item-action', function() {
    const productId = $(this).data('id');
    const productName = $(this).data('name');

    $suggestions.hide().empty();
    $search.val(productName);

    // Fetch batches (top 10)
    $.getJSON('../ajax/get_product_batches.php', { product_id: productId }, function(rows) {
      const $tbody = $('#batchTable tbody');
      $tbody.empty();

      if (!rows || !rows.length) {
        $tbody.append('<tr><td colspan="11" class="text-center text-muted">No batches available.</td></tr>');
      } else {
        rows.forEach(r => {
          const tr = $(`
            <tr>
              <td><button type="button" class="btn btn-sm btn-success select-batch">Select</button></td>
              <td>${r.product_name}</td>
              <td>${r.batch_no}</td>
              <td>${r.hsn_code || ''}</td>
              <td>${r.pack || ''}</td>
              <td>${r.expiry_date || ''}</td>
              <td>${parseFloat(r.mrp || 0).toFixed(2)}</td>
              <td>${parseFloat(r.rate || 0).toFixed(2)}</td>
              <td>${parseFloat(r.sgst || 0).toFixed(2)}</td>
              <td>${parseFloat(r.cgst || 0).toFixed(2)}</td>
              <td>${parseFloat(r.igst || 0).toFixed(2)}</td>
              <td class="d-none">
                <input type="hidden" class="pm-id" value="${r.id}">
              </td>
            </tr>
          `);
          $tbody.append(tr);
        });
      }
      $('#batchTableModal').modal('show');
    });
  });

  // “No results → Add product”
  $suggestions.on('click', '#suggestAddProduct', function(e) {
    e.stopPropagation();
    $suggestions.hide().empty();
    // preload name into modal
    const name = $search.val().trim();
    $('#addProductForm')[0].reset();
    $('#addProductForm [name="product_name"]').val(name);
    $('#addProductModal').modal('show');
  });

  // Add product (Ajax save)
  $('#addProductForm').on('submit', function() {
    const data = $(this).serialize();
    $.post('../ajax/add_product.php', data, function(resp) {
      if (resp && resp.success) {
        $('#addProductModal').modal('hide');
        // Immediately show the batch chooser with the newly added product
        $search.val(resp.product.product_name);
        // simulate suggestion click: fetch batches for new product
        $.getJSON('../ajax/get_product_batches.php', { product_id: resp.product.id }, function(rows) {
          const $tbody = $('#batchTable tbody');
          $tbody.empty();
          rows.forEach(r => {
            const tr = $(`
              <tr>
                <td><button type="button" class="btn btn-sm btn-success select-batch">Select</button></td>
                <td>${r.product_name}</td>
                <td>${r.batch_no}</td>
                <td>${r.hsn_code || ''}</td>
                <td>${r.pack || ''}</td>
                <td>${r.expiry_date || ''}</td>
                <td>${parseFloat(r.mrp || 0).toFixed(2)}</td>
                <td>${parseFloat(r.rate || 0).toFixed(2)}</td>
                <td>${parseFloat(r.sgst || 0).toFixed(2)}</td>
                <td>${parseFloat(r.cgst || 0).toFixed(2)}</td>
                <td>${parseFloat(r.igst || 0).toFixed(2)}</td>
                <td class="d-none"><input type="hidden" class="pm-id" value="${r.id}"></td>
              </tr>
            `);
            $tbody.append(tr);
          });
          $('#batchTableModal').modal('show');
        });
      } else {
        alert(resp && resp.message ? resp.message : 'Unable to save product.');
      }
    }, 'json').fail(() => {
      alert('Server error while saving product.');
    });
  });

  // Select batch → add to purchase table
  $('#batchTable').on('click', '.select-batch', function() {
    const $tr = $(this).closest('tr');
    const productName = $tr.children().eq(1).text();
    const batch = $tr.children().eq(2).text();
    const hsn = $tr.children().eq(3).text();
    const pack = $tr.children().eq(4).text();
    const expiry = $tr.children().eq(5).text();
    const mrp = parseFloat($tr.children().eq(6).text()) || 0;
    const rate = parseFloat($tr.children().eq(7).text()) || 0;
    const sgst = parseFloat($tr.children().eq(8).text()) || 0;
    const cgst = parseFloat($tr.children().eq(9).text()) || 0;
    const igst = parseFloat($tr.children().eq(10).text()) || 0;
    const pmId = $tr.find('.pm-id').val();

    // Append row to your purchase table (editable)
    const rowHtml = `
      <tr>
        <td><input type="text" class="form-control" name="product_name[]" value="${productName}" readonly></td>
        <td><input type="text" class="form-control" name="pack[]" value="${pack}"></td>
        <td><input type="text" class="form-control" name="batch[]" value="${batch}"></td>
        <td><input type="text" class="form-control" name="hsn[]" value="${hsn}"></td>
        <td><input type="date" class="form-control" name="exp_date[]" value="${expiry && expiry.includes('-') ? expiry : ''}"></td>
        <td><input type="number" step="0.01" class="form-control rates" name="mrp[]" value="${mrp.toFixed(2)}"></td>
        <td><input type="number" step="0.01" class="form-control rates" name="rate[]" value="${rate.toFixed(2)}"></td>
        <td><input type="number" class="form-control quantities" name="quantity[]" value="1"></td>
        <td><input type="number" step="0.01" class="form-control" name="discount[]" value="0"></td>
        <td><input type="number" class="form-control" name="free_quantity[]" value="0"></td>
        <td><input type="text" class="form-control" name="composition[]" value=""></td>
        <td><input type="number" step="0.01" class="form-control" name="igst[]" value="${igst.toFixed(2)}"></td>
        <td><input type="number" step="0.01" class="form-control" name="cgst[]" value="${cgst.toFixed(2)}"></td>
        <td><input type="number" step="0.01" class="form-control" name="sgst[]" value="${sgst.toFixed(2)}"></td>
        <td><input type="number" step="0.01" class="form-control total" name="total[]" value="${rate.toFixed(2)}" readonly></td>
        <td><input type="hidden" name="product_master_id[]" value="${pmId}"></td>
      </tr>
    `;
    $('#productDetails').append(rowHtml);

    // Close modal and refocus search
    $('#batchTableModal').modal('hide');
    setTimeout(() => { $('#product_search').val('').focus(); }, 300);

    // Trigger your running totals if you have them
    $('#purchaseForm').trigger('input'); // or call your updateTotals()
  });

  // Open add product modal explicitly
  $('#btnAddProduct').on('click', function(){
    $('#addProductForm')[0].reset();
    $('#addProductForm [name="product_name"]').val($('#product_search').val().trim());
    $('#addProductModal').modal('show');
  });

  // Close suggestions when clicking outside
  $(document).on('click', function(e){
    if (!$(e.target).closest('.product-picker').length) {
      $suggestions.hide().empty();
    }
  });
})(jQuery);
