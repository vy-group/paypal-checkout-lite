jQuery(function ($) {
	$(document).ready(function () {
		setTimeout(function () {
			var options = {
				createOrder: function (data, actions) {
					// This function sets up the details of the transaction, including the amount and line item details.
					console.log(woocommerce);
					return actions.order.create({
						purchase_units: JSON.parse(woocommerce.content),
					});
				},
				onApprove: function (data, actions) {
					// This function captures the funds from the transaction.
					return actions.order.capture().then(function (details) {
						console.log(details);
						var form = new FormData();
						form.append("email", details.payer.email_address);
						form.append(
							"first_name",
							details.payer.name.given_name
						);
						form.append("last_name", details.payer.name.surname);
						form.append(
							"address_1",
							details.purchase_units[0].shipping.address
								.address_line_1
						);
						form.append(
							"city",
							details.purchase_units[0].shipping.address
								.admin_area_2
						);
						form.append(
							"country",
							details.purchase_units[0].shipping.address
								.country_code
						);
						form.append(
							"postcode",
							details.purchase_units[0].shipping.address
								.postal_code
						);
						fetch("/?wc-api=wc_paypal_checkout_lite", {
							method: "post",
							body: form,
						}).then(function (res) {
							console.log(res);
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
