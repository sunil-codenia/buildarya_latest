<div class="modal fade" id="upgradePlanModal" tabindex="-1" role="dialog" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content text-center" style="border-radius: 16px; overflow: hidden; border: none; box-shadow: 0 15px 35px rgba(0,0,0,0.2);">
            <div class="modal-header text-white" style="background: linear-gradient(135deg, #1f1b2d 0%, #3b2f54 100%); border: none; padding: 25px 20px;">
                <h5 class="modal-title font-weight-bold" style="width: 100%; margin: 0; font-size: 1.5rem; letter-spacing: 0.5px; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <i class="zmdi zmdi-trending-up text-warning" style="font-size: 28px;"></i> Upgrade Your Capacity
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close" style="position: absolute; right: 20px; top: 25px; opacity: 0.8; outline: none; background: none; border: none;">
                    <span aria-hidden="true" style="font-size: 1.8rem;">&times;</span>
                </button>
            </div>
            <div class="modal-body" style="padding: 30px; background-color: #f8f9fa;">
                <p class="text-muted mb-4" style="font-size: 1.1rem; line-height: 1.6;">
                    You have reached the maximum allowed limit for users/sites on your current subscription plan. 
                    Choose from our monthly addon options below to scale up instantly.
                </p>

                <div class="row">
                    <!-- Extra Users Addon -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-0" style="border-radius: 12px; transition: transform 0.3s; background: white;">
                            <div class="card-body p-4 text-center">
                                <div style="width: 60px; height: 60px; background: rgba(237, 166, 26, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                    <i class="zmdi zmdi-accounts-add text-warning" style="font-size: 28px;"></i>
                                </div>
                                <h4 class="font-weight-bold mb-2" style="color: #2b2b2b; font-size: 1.3rem;">Extra Users</h4>
                                <p class="text-muted text-sm mb-3" style="min-height: 40px;">Add more team members to manage your sites.</p>
                                <h3 class="font-weight-bold text-primary mb-4" style="font-size: 1.8rem; color: #eda61a !important;">
                                    ₹100 <span style="font-size: 1rem; font-weight: normal; color: #777;">/ user / mo</span>
                                </h3>
                                
                                <div class="form-group mb-4">
                                    <label class="text-sm font-weight-bold text-muted d-block mb-2">Quantity</label>
                                    <div class="input-group justify-content-center" style="max-width: 150px; margin: 0 auto;">
                                        <div class="input-group-prepend">
                                            <button class="btn btn-outline-secondary btn-sm px-3" type="button" onclick="adjustQty('user', -1)" style="border-radius: 20px 0 0 20px; font-weight: bold; height: 34px;">-</button>
                                        </div>
                                        <input type="number" id="addon-user-qty" class="form-control text-center font-weight-bold" value="1" min="1" readonly style="border-left: none; border-right: none; height: 34px; background: white; max-width: 60px;">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary btn-sm px-3" type="button" onclick="adjustQty('user', 1)" style="border-radius: 0 20px 20px 0; font-weight: bold; height: 34px;">+</button>
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-warning btn-block btn-round font-weight-bold py-2 shadow-sm" onclick="payAddon('user')" style="color: white; background-color: #eda61a; border: none; border-radius: 30px; font-size: 1rem;">
                                    Pay Extra Users
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Extra Sites Addon -->
                    <div class="col-md-6 mb-3">
                        <div class="card h-100 shadow-sm border-0" style="border-radius: 12px; transition: transform 0.3s; background: white;">
                            <div class="card-body p-4 text-center">
                                <div style="width: 60px; height: 60px; background: rgba(59, 47, 84, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                                    <i class="zmdi zmdi-city text-primary" style="font-size: 28px; color: #3b2f54 !important;"></i>
                                </div>
                                <h4 class="font-weight-bold mb-2" style="color: #2b2b2b; font-size: 1.3rem;">Extra Sites</h4>
                                <p class="text-muted text-sm mb-3" style="min-height: 40px;">Add more construction sites to your dashboard.</p>
                                <h3 class="font-weight-bold text-primary mb-4" style="font-size: 1.8rem; color: #3b2f54 !important;">
                                    ₹200 <span style="font-size: 1rem; font-weight: normal; color: #777;">/ site / mo</span>
                                </h3>

                                <div class="form-group mb-4">
                                    <label class="text-sm font-weight-bold text-muted d-block mb-2">Quantity</label>
                                    <div class="input-group justify-content-center" style="max-width: 150px; margin: 0 auto;">
                                        <div class="input-group-prepend">
                                            <button class="btn btn-outline-secondary btn-sm px-3" type="button" onclick="adjustQty('site', -1)" style="border-radius: 20px 0 0 20px; font-weight: bold; height: 34px;">-</button>
                                        </div>
                                        <input type="number" id="addon-site-qty" class="form-control text-center font-weight-bold" value="1" min="1" readonly style="border-left: none; border-right: none; height: 34px; background: white; max-width: 60px;">
                                        <div class="input-group-append">
                                            <button class="btn btn-outline-secondary btn-sm px-3" type="button" onclick="adjustQty('site', 1)" style="border-radius: 0 20px 20px 0; font-weight: bold; height: 34px;">+</button>
                                        </div>
                                    </div>
                                </div>

                                <button class="btn btn-primary btn-block btn-round font-weight-bold py-2 shadow-sm" onclick="payAddon('site')" style="background: linear-gradient(135deg, #1f1b2d 0%, #3b2f54 100%); border: none; border-radius: 30px; font-size: 1rem; color: white;">
                                    Pay Extra Sites
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer justify-content-center bg-white border-0" style="padding: 20px;">
                <button type="button" class="btn btn-neutral btn-round" data-dismiss="modal" style="font-weight: bold; border: 1px solid #ddd; padding: 8px 25px; border-radius: 20px;">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script type="text/javascript">
    function adjustQty(type, change) {
        var input = document.getElementById('addon-' + type + '-qty');
        var val = parseInt(input.value) + change;
        if (val < 1) val = 1;
        input.value = val;
    }

    function payAddon(type) {
        var qty = parseInt(document.getElementById('addon-' + type + '-qty').value);
        var unitPrice = (type === 'user') ? 100 : 200;
        var totalAmount = unitPrice * qty;

        Swal.fire({
            title: 'Confirm Addon Purchase',
            text: 'Proceed to pay ₹' + totalAmount + ' for ' + qty + ' extra ' + (type === 'user' ? 'user(s)' : 'site(s)') + ' for 1 month?',
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#eda61a',
            cancelButtonColor: '#000000',
            confirmButtonText: 'Pay Now',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Initiating Payment...',
                    text: 'Please wait while we connect to Razorpay',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                // 1. Create Razorpay Order
                $.ajax({
                    url: '{{ url("/invoices/create-addon-order") }}',
                    method: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        type: type,
                        quantity: qty
                    },
                    success: function(order) {
                        Swal.close();
                        if (order.error) {
                            Swal.fire('Error', order.error, 'error');
                            return;
                        }

                        // 2. Open Razorpay Checkout Modal
                        var options = {
                            key: order.key_id,
                            amount: order.amount,
                            currency: order.currency,
                            name: 'Buildarya Addons',
                            description: 'Purchase ' + qty + ' extra ' + (type === 'user' ? 'users' : 'sites'),
                            order_id: order.id,
                            handler: function(response) {
                                Swal.fire({
                                    title: 'Verifying Payment...',
                                    text: 'Please do not close this window',
                                    allowOutsideClick: false,
                                    didOpen: () => {
                                        Swal.showLoading();
                                    }
                                });

                                // 3. Finalize Payment
                                $.ajax({
                                    url: '{{ url("/invoices/finalize-addon-payment") }}',
                                    method: 'POST',
                                    data: {
                                        _token: '{{ csrf_token() }}',
                                        razorpay_payment_id: response.razorpay_payment_id,
                                        razorpay_order_id: response.razorpay_order_id,
                                        razorpay_signature: response.razorpay_signature,
                                        type: type,
                                        quantity: qty,
                                        amount: totalAmount
                                    },
                                    success: function(res) {
                                        if (res.success) {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Success!',
                                                text: 'Addon activated successfully. Page will reload.',
                                                timer: 2000,
                                                showConfirmButton: false
                                            }).then(() => {
                                                window.location.reload();
                                            });
                                        } else {
                                            Swal.fire('Activation Failed', res.error || 'Please contact support.', 'error');
                                        }
                                    },
                                    error: function(err) {
                                        Swal.fire('Error', 'Payment verification failed. Please contact support.', 'error');
                                    }
                                });
                            },
                            prefill: {
                                name: '{{ session()->get("name") }}',
                                email: '{{ session()->get("comp_email") }}',
                                contact: '{{ session()->get("comp_mobile") }}'
                            },
                            theme: {
                                color: '#eda61a'
                            }
                        };

                        var rzp = new Razorpay(options);
                        rzp.on('payment.failed', function(resp) {
                            Swal.fire('Payment Failed', resp.error.description || 'Transaction declined.', 'error');
                        });
                        rzp.open();
                    },
                    error: function(err) {
                        Swal.close();
                        Swal.fire('Connection Error', 'Could not create order. Please try again.', 'error');
                    }
                });
            }
        });
    }

    $(document).ready(function() {
        @if(session('error') == 'Upgrade your plan' || (isset($user_limit_reached) && $user_limit_reached) || (isset($site_limit_reached) && $site_limit_reached))
            setTimeout(function() {
                $('#upgradePlanModal').modal('show');
            }, 500);
        @endif
    });
</script>
