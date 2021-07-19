const is_gb_postcode = function (to_check) {
  // Permitted letters depend upon their position in the postcode.
  // https://en.wikipedia.org/wiki/Postcodes_in_the_United_Kingdom#Validation.
  let alpha1 = "[abcdefghijklmnoprstuwyz]"; // Character 1.
  let alpha2 = "[abcdefghklmnopqrstuvwxy]"; // Character 2.
  let alpha3 = "[abcdefghjkpstuw]"; // Character 3 == ABCDEFGHJKPSTUW.
  let alpha4 = "[abehmnprvwxy]"; // Character 4 == ABEHMNPRVWXY.
  let alpha5 = "[abdefghjlnpqrstuwxyz]"; // Character 5 != CIKMOV.

  let pcexp = array();

  // Expression for postcodes: AN NAA, ANN NAA, AAN NAA, and AANN NAA.
  pcexp[0] = new RegExp(
    "/^(" +
      $alpha1 +
      "{1}" +
      $alpha2 +
      "{0,1}[0-9]{1,2})([0-9]{1}" +
      $alpha5 +
      "{2})$/"
  );

  // Expression for postcodes: ANA NAA.
  pcexp[1] = new RegExp(
    "/^(" +
      $alpha1 +
      "{1}[0-9]{1}" +
      $alpha3 +
      "{1})([0-9]{1}" +
      $alpha5 +
      "{2})$/"
  );

  // Expression for postcodes: AANA NAA.
  pcexp[2] = new RegExp(
    "/^(" +
      $alpha1 +
      "{1}" +
      $alpha2 +
      "[0-9]{1}" +
      $alpha4 +
      ")([0-9]{1}" +
      $alpha5 +
      "{2})$/"
  );

  // Exception for the special postcode GIR 0AA.
  pcexp[3] = new RegExp("/^(gir)(0aa)$/");

  // Standard BFPO numbers.
  pcexp[4] = new RegExp("/^(bfpo)([0-9]{1,4})$/");

  // c/o BFPO numbers.
  pcexp[5] = new RegExp("/^(bfpo)(c/o[0-9]{1,3})$/");

  // Load up the string to check, converting into lowercase and removing spaces.
  postcode = to_check.toLowerCase();
  postcode = postcode.replaceAll(" ", "");

  // Assume we are not going to find a valid postcode.
  let correct = false;

  // Check the string against the six types of postcodes.
  pcexp.forEach((exp) => {
    if (exp.test(postcode)) {
      // Remember that we have found that the code is valid and break from loop.
      correct = true;
      return;
    }
  });

  return correct;
};

const is_post_code = function (postcode, country) {
  if (postcode.replaceAll(/[\s\-A-Za-z0-9]/g, "").trim().length > 0) {
    return false;
  }
  let correct;
  switch (country) {
    case "AT":
      correct = /^([0-9]{4})$/.test(postcode);
      break;
    case "BA":
      correct = /^([7-8]{1})([0-9]{4})$/.test(postcode);
      break;
    case "BE":
      correct = /^([0-9]{4})$/i.test(postcode);
      break;
    case "BR":
      correct = /^([0-9]{5})([-])?([0-9]{3})$/.test(postcode);
      break;
    case "CH":
      correct = /^([0-9]{4})$/i.test(postcode);
      break;
    case "DE":
      correct = /^([0]{1}[1-9]{1}|[1-9]{1}[0-9]{1})[0-9]{3}$/.test(postcode);
      break;
    case "ES":
    case "FR":
    case "IT":
      correct = /^([0-9]{5})$/i.test(postcode);
      break;
    case "GB":
      correct = is_gb_postcode(postcode);
      break;
    case "HU":
      correct = /^([0-9]{4})$/i.test(postcode);
      break;
    case "IE":
      correct = /([AC-FHKNPRTV-Y]\d{2}|D6W)[0-9AC-FHKNPRTV-Y]{4}/.test(
        postcode
      );
      break;
    case "IN":
      correct = /^[1-9]{1}[0-9]{2}\s{0,1}[0-9]{3}$/.test(postcode);
      break;
    case "JP":
      correct = /^([0-9]{3})([-]?)([0-9]{4})$/.test(postcode);
      break;
    case "PT":
      correct = /^([0-9]{4})([-])([0-9]{3})$/.test(postcode);
      break;
    case "PR":
    case "US":
      correct = /^([0-9]{5})(-[0-9]{4})?$/i.test(postcode);
      break;
    case "CA":
      // CA Postal codes cannot contain D,F,I,O,Q,U and cannot start with W or Z. https://en.wikipedia.org/wiki/Postal_codes_in_Canada#Number_of_possible_postal_codes.
      correct = /^([ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ])([\ ])?(\d[ABCEGHJKLMNPRSTVWXYZ]\d)$/i.test(
        postcode
      );
      break;
    case "PL":
      correct = /^([0-9]{2})([-])([0-9]{3})$/.test(postcode);
      break;
    case "CZ":
    case "SK":
      correct = /^([0-9]{3})(\s?)([0-9]{2})$/.test(postcode);
      break;
    case "NL":
      correct = /^([1-9][0-9]{3})(\s?)(?!SA|SD|SS)[A-Z]{2}$/i.test(postcode);
      break;
    case "SI":
      correct = /^([1-9][0-9]{3})$/.test(postcode);
      break;
    case "LI":
      correct = /^(94[8-9][0-9])$/.test(postcode);
      break;
    default:
      correct = true;
      break;
  }
  return correct;
};
const validate_checkout = function () {
    let valid = true;
    let customerData = Array.from(
        billing_form_card.querySelectorAll("input,select")
    );
    customerData.forEach((e) => {
        if (!valid) return;
        switch (e.name) {
            case "billing_first_name": {
                valid = !!e.value;
                break;
            }
            case "billing_last_name": {
                valid = !!e.value;
                break;
            }
            case "billing_address_1": {
                valid = !!e.value;
                break;
            }
            case "billing_city": {
                valid = !!e.value;
                break;
            }
            case "billing_country": {
                valid = !!e.value;
                break;
            }
            case "billing_state": {
                valid = !!e.value;
                /*&& states.includes(e.value);*/ break;
            }
            case "billing_postcode": {
                let billing_country = document.getElementById("billing_postcode").value;
                valid = !!e.value && is_post_code(e.value, billing_country);
                break;
            }
            case "billing_phone": {
                valid =
                !!e.value &&
                0 == e.value.replaceAll(/[\s\#0-9_\-\+\/\(\)\.]/g, "").trim().length;
                break;
            }
            case "billing_email": {
                valid = !!e.value && /\S+@\S+\.\S+/.test(e.value);
                break;
            }
        }
    });
    if (valid) {
        let phone = jQuery("#billing_phone");
        let first_name = jQuery("#billing_first_name");
        let last_name = jQuery("#billing_last_name");
        let email = jQuery("#billing_email");
        let country = jQuery("#billing_country");
        let postcode = jQuery("#billing_postcode");
        let address_1 = jQuery("#billing_address_1");
        let address_2 = jQuery("#billing_address_2");
        let company = jQuery("#billing_company");
        let state = jQuery("#billing_state");
        let city = jQuery('#billing_city');
        let postBody = {
            action: "ckpg_create_order",  
            phone: phone.val(),
            firstName: first_name.val(),
            lastName: last_name.val(),
            city: city.val(),
            email: email.val(),
            country: country.val(),
            postcode: postcode.val(),
            address_1: address_1.val(),
            address_2: address_2.val(),
            company: company.val(),
            state: state.val()
        }
        let error_container = document.getElementById("conektaBillingFormErrorMessage");
        jQuery.post(
            tokenize.ajaxurl,
            postBody,
            function (response) {
                if(response.error){
                    error_container.innerText = response.error
                }else{
                    let container = document.getElementById("conektaIframeContainer");
                    container.innerHTML = '';
                    container.style.display = "block";
                    container.style.height = "90rem";
                    error_container.style.display = "none";
                    window.ConektaCheckoutComponents.Integration({
                        targetIFrame: `#conektaIframeContainer`,
                        checkoutRequestId: response.checkout_id,
                        publicKey: response.key,
                        paymentMethods: ["Cash", "Card", "BankTransfer"],
                        options: {
                        button: {
                            buttonPayText: `Pago de $${response.price}`,
                        },
                        paymentMethodInformation: {
                            bankTransferText: response.spei_text,
                            cashText: response.cash_text,
                            display: true,
                        },
                        theme: "default", // 'blue' | 'dark' | 'default' | 'green' | 'red'
                        styles: {
                            fontSize: "baseline", // 'baseline' | 'compact'
                            inputType: "rounded", // 'basic' | 'rounded' | 'line'
                            buttonType: "sharp", // 'basic' | 'rounded' | 'sharp'
                        },
                        },
                        onFinalizePayment: function (order) {
                          let method = (order && order.charge && order.charge.payment_method) ? order.charge.payment_method.type : null;
                          let charge_id = (order && order.charge) ? order.charge.id : null;
                          let form = jQuery('form[name=checkout]');
                          form.append(jQuery('<input type="hidden" name="conekta_payment_method" id="conekta_payment_method" />').val(method));
                          form.append(jQuery('<input type="hidden" name="charge_id" id="charge_id" />').val(charge_id));
                          switch(method){
                            case "oxxo": 
                              form.append(jQuery('<input type="hidden" name="reference" id="reference" />').val(order.reference)); break;
                            case "spei": 
                              form.append(jQuery('<input type="hidden" name="clabe" id="clabe" />').val(order.reference)); break;
                          }
                          document.getElementById("place_order").click();
                        },
                    });
                }
            }
        )
        .error(function (e) {
          console.error(e)
        });
    } else {
        let error_container = document.getElementById("conektaBillingFormErrorMessage");
        error_container.style.display = "block";
        let container = document.getElementById("conektaIframeContainer");
        container.style.display = "none";
    }
};
let billing_form_card = document.getElementById("customer_details");
if(billing_form_card){
  Array.from(billing_form_card.querySelectorAll("input,select")).forEach((e) => {
    e.addEventListener("change", validate_checkout);
  });
}
window.onload = () => {if(billing_form_card){validate_checkout()}}
