jQuery(function ($) {
	$(document).ready(function () {
		setTimeout(function () {
			console.log(woocommerce);
			var options = {
				createOrder: function (data, actions) {
					var checkout_form = $("form.woocommerce-checkout");

					checkout_form.submit();
					// This function sets up the details of the transaction, including the amount and line item details.
					return actions.order.create({
						purchase_units: JSON.parse(woocommerce.content),
					});
				},
				onApprove: function (data, actions) {
					// This function captures the funds from the transaction.
					return actions.order.capture().then(function (details) {
						console.log(details);
						var form = new FormData();
						form.append("order_id", woocommerce.order_id);
						fetch("/wc-api/paypal_checkout_lite", {
							method: "post",
							body: form,
						});
						// This function shows a transaction success message to your buyer.
					});
				},
			};
			window.paypal.Buttons(options).render("#paypal-button-container");
		}, 250);
	});
});

// This function displays Smart Payment Buttons on your web page.
