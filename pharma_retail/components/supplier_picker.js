$(document).ready(function () {
  // Autocomplete supplier search
  $("#supplier_name").autocomplete({
    source: function (request, response) {
      $.ajax({
        url: "../ajax/search_supplier.php",
        dataType: "json",
        data: { q: request.term },
        success: function (data) {
          if (data.length) {
            response($.map(data, function (item) {
              return { label: item.supplier_name, value: item.supplier_name, id: item.sup_id };
            }));
          } else {
            response([{ label: "➕ Add new supplier", value: "", id: 0 }]);
          }
        }
      });
    },
    minLength: 2,
    select: function (event, ui) {
      if (ui.item.id === 0) {
        $("#addSupplierModal").modal("show");
      } else {
        $("#sup_id").val(ui.item.id);
        $("#supplier_name").val(ui.item.value);
      }
    }
  });

  // Save supplier via modal
  $(document).on("submit", "#addSupplierForm", function (e) {
    e.preventDefault();

    $.post('../ajax/ajax_add_supplier.php', $(this).serialize(), function (response) {
      if (response.success) {
        $("#sup_id").val(response.id);
        $("#supplier_name").val(response.name);
        $("#addSupplierModal").modal("hide");
        $("#addSupplierForm")[0].reset();
      } else {
        alert("❌ " + response.message);
      }
    }, "json").fail(function (xhr) {
      alert("❌ AJAX error: " + xhr.responseText);
    });
  });

  // Open modal from ➕ button
  $("#btnAddSupplier").click(function () {
    $("#addSupplierModal").modal("show");
  });
});
