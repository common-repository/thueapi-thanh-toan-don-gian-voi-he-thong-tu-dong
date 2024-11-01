<?php

if (! defined('ABSPATH')) {
    exit('Code your dream');
}

class ThueAPI_Gateway extends WC_Payment_Gateway
{
    public function __construct()
    {
        $this->id = 'thueapi';
        $this->icon = $this->get_option('bankIcon') ?: apply_filters('woocommerce_bacs_icon', plugin_dir_url(__DIR__).'assets/images/bank.svg');
        $this->has_fields = false;
        $this->method_title = __('ThueAPI - Thanh toán đơn giản với hệ thống tự động !', 'woocommerce');
        $this->method_description = __('Giải pháp xử lý giao dịch tự động cho đơn hàng thanh toán bằng hình thức chuyển khoản qua các ngân hàng tại Việt Nam. <br/>Các ngân hàng thông dụng như: Vietcombank, Techcombank, ACB, Momo, MBBank, TPBank, VPBank...', 'woocommerce');

        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->receivedOrderTextBeforePaid = $this->get_option('receivedOrderTextBeforePaid');
        $this->receivedOrderTextAfterPaid = $this->get_option('receivedOrderTextAfterPaid');
        $this->transferGuide = $this->get_option('transferGuide');

        $this->token = $this->get_option('token');

        if (strlen($this->token) < 50) {
            $this->update_option('token', $this->randomString());
        }

        $this->bankAccounts = get_option('woocommerce_bacs_accounts', [
            [
                'account_name' => $this->get_option('account_name'),
                'account_number' => $this->get_option('account_number'),
                'bank_name' => $this->get_option('bank_name'),
            ],
        ]);

        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_update_options_payment_gateways_'.$this->id, [$this, 'updateBankAccounts']);

        if (! has_filter('woocommerce_thankyou_order_received_text')) {
            add_action('woocommerce_thankyou_'.$this->id, [$this, 'showThankyouPage']);
        } else {
            add_filter('woocommerce_thankyou_order_received_text', [$this, 'thankyouPage'], 10, 2);
        }

        add_action('woocommerce_api_'.$this->id, [$this, 'paymentProcess']);
        add_action('admin_enqueue_scripts', function () {
            wp_enqueue_media();
        });

        add_filter('woocommerce_order_is_paid_statuses', [$this, 'woocommerceOrderIsPaidStatuses'], 10, 2);
    }

    public function init_form_fields()
    {
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable/Disable', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('ThueAPI', 'woocommerce'),
                'default' => 'no',
            ],
            'title' => [
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => 'Chuyển khoản ngân hàng 24/7',
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default' => 'Thực hiện thanh toán chuyển khoản vào tài khoản ngân hàng của chúng tôi. Vui lòng điền Mã đơn hàng của bạn trong phần Nội dung thanh toán. Đơn hàng của bạn sẽ đươc xác nhận tự động ngay sau khi tài khoản ngân hàng của chúng tôi nhận được tiền.',
                'desc_tip' => true,
            ],
            'placeOrderButtonLabel' => [
                'title' => __('Nút tới trang thanh toán', 'woocommerce'),
                'type' => 'text',
                'default' => 'Tới trang thanh toán',
            ],
            'receivedOrderTextBeforePaid' => [
                'title' => __('Thông báo trước khi thanh toán', 'woocommerce'),
                'type' => 'textarea',
                'description' => 'Thông báo sẽ hiển thị ở trang Order Received',
                'default' => '<h2>Hãy chọn ngân hàng để chuyển khoản</h2>',
                'desc_tip' => true,
            ],
            'receivedOrderTextAfterPaid' => [
                'title' => __('Thông báo sau khi thanh toán', 'woocommerce'),
                'type' => 'textarea',
                'description' => 'Thông báo sẽ hiển thị ở trang Order Received',
                'default' => '<h2>Cám ơn bạn đã đặt hàng trên website của chúng tôi !</h2>',
                'desc_tip' => true,
            ],
            'transferGuide' => [
                'title' => __('Hướng dẫn thanh toán', 'woocommerce'),
                'type' => 'textarea',
                'default' => 'Vui lòng chuyển khoản theo thông tin:<br />Chủ tài khoản: %accountName<br />Số tài khoản: %account <br />Ngân hàng: %bank<br />Chi nhánh Ngân hàng: %branch<br />LƯU Ý: Nội dung chuyển khoản GHI CHÍNH XÁC LÀ %order cùng số tiền %amount để hệ thống xác nhận đơn hàng tự động cho giao dịch của bạn.',
                'desc_tip' => true,
                'description' => 'Các biến sử dụng: Chủ tài khoản: %accountName, Số tài khoản: %account, Ngân hàng: %bank, Chi nhánh Ngân hàng: %branch, Mã đơn hàng: %order, Số tiền %amount',
            ],
            'showTextGuide' => [
                'title' => __('Hiển thị nút "Xem hướng dẫn thanh toán"', 'woocommerce'),
                'type' => 'checkbox',
                'label' => __('Yes', 'woocommerce'),
                'default' => 'yes',
            ],
            'guideText' => [
                'title' => __('Xem hướng dẫn thanh toán', 'woocommerce'),
                'type' => 'text',
                'default' => 'XEM HƯỚNG DẪN THANH TOÁN',
                'desc_tip' => true,
            ],
            'guideLink' => [
                'title' => __('Link xem hướng dẫn thanh toán', 'woocommerce'),
                'type' => 'text',
                'default' => 'https://',
                'desc_tip' => true,
            ],
            'bankAccounts' => [
                'type' => 'bankAccounts',
            ],
            'bankIcon' => [
                'title' => __('Bank Icon', 'woocommerce'),
                'type' => 'text',
                'description' => '',
                'default' => __('', 'woocommerce'),
                'desc_tip' => false,
                'class' => 'uploadinput',
            ],
            'vietQrStyle' => [
                'title' => __('VietQR.CO Style Generator'),
                'type' => 'select',
                'description' => __('Kiểu hiển thị mã QR.', 'woocommerce'),
                'default' => '1',
                'class' => 'status_type wc-enhanced-select',
                'options' => [
                    1 => 'Background ngẫu nhiên',
                    2 => 'Chỉ hiển thị mã QRCODE',
                ],
                'desc_tip' => true,

            ],
            'vietQrStyleLogo' => [
                'title' => __('VietQR.CO QRCode Logo'),
                'type' => 'checkbox',
                'label' => __('Yes', 'woocommerce'),
                'description' => __('Hiển thị logo của ngân hàng bên dưới QRCODE.', 'woocommerce'),
                'default' => 'yes',
            ],
            'overrideThankyouHook' => [
                'title' => __('Thankyou Hook Content'),
                'type' => 'checkbox',
                'label' => __('Yes', 'woocommerce'),
                'description' => __('Giữ nội dung từ Thankyou Hook.', 'woocommerce'),
                'default' => 'yes',
            ],
            'token' => [
                'title' => __('Token', 'woocommerce'),
                'type' => 'text',
                'description' => 'Token',
                'default' => '',
                'required' => 'true',
                'desc_tip' => true,
            ],
            'syntaxPrefix' => [
                'title' => __('Ký tự định danh', 'woocommerce'),
                'type' => 'text',
                'description' => 'Ký tự này giúp định danh và nhận biết các giao dịch thanh toán cho đơn hàng',
                'default' => 'DH',
                'required' => 'true',
                'desc_tip' => true,
            ],
            'orderStatusAfterPaid' => [
                'title' => __('Trạng thái thanh toán đủ'),
                'type' => 'select',
                'description' => __('Vui lòng chọn một trạng thái sau khi thanh toán đủ.', 'woocommerce'),
                'default' => 'wc-completed',
                'class' => 'status_type wc-enhanced-select',
                'options' => wc_get_order_statuses(),
                'desc_tip' => true,

            ],
            'orderStatusAfterOverPaid' => [
                'title' => __('Trạng thái thanh toán dư'),
                'type' => 'select',
                'description' => __('Vui lòng chọn một trạng thái sau khi thanh toán dư.', 'woocommerce'),
                'default' => 'wc-over-paid',
                'class' => 'status_type wc-enhanced-select',
                'options' => wc_get_order_statuses(),
                'desc_tip' => true,

            ],
            'orderStatusAfterLessPaid' => [
                'title' => __('Trạng thái thanh toán thiếu'),
                'type' => 'select',
                'description' => __('Vui lòng chọn một trạng thái sau khi thanh toán thiếu.', 'woocommerce'),
                'default' => 'wc-less-paid',
                'class' => 'status_type wc-enhanced-select',
                'options' => wc_get_order_statuses(),
                'desc_tip' => true,

            ],
            'forceShowPaymentWhenOrderStatuses' => [
                'title' => __('Luôn hiển thị phương thức thanh toán đối với trạng thái'),
                'type' => 'multiselect',
                'description' => __('Luôn hiển thị phương thức thanh toán nếu đơn hàng thuộc các trạng thái trong danh sách.', 'woocommerce'),
                'default' => '',
                'class' => 'status_type wc-enhanced-select',
                'options' => wc_get_order_statuses(),
                'desc_tip' => false,

            ],
        ];
    }

    private function randomString($length = 50)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }

    public function paymentProcess()
    {
        if (isset($_REQUEST['action']) && $_REQUEST['action'] === 'checkClientPayment') {
            $orderId = (int) preg_replace('#(\D+)#', '', $_REQUEST['order']);

            $order = wc_get_order($orderId);

            if (! $order) {
                wp_send_json([
                    'success' => false,
                    'message' => 'No order found !',
                ]);
            }

            if ($order->is_paid()) {
                wp_send_json([
                    'success' => true,
                    'message' => 'Order paid successfully',
                ]);
            }

            wp_send_json([
                'success' => false,
                'message' => 'waiting payment...',
            ]);
        } else {
            $raw = file_get_contents('php://input');

            $transaction = json_decode($raw);

            if (json_last_error() !== JSON_ERROR_NONE) {
                wp_send_json([
                    'success' => false,
                    'message' => 'No data !',
                ]);
            }

            $token = $this->getHeader('X-THUEAPI');

            if ($token !== $this->token) {
                wp_send_json([
                    'success' => false,
                    'message' => 'Token incorrect !',
                ]);
            }

            if (empty($transaction->txn_id)) {
                wp_send_json([
                    'success' => false,
                    'message' => 'No transaction txn id !',
                ]);
            }

            if (! preg_match(sprintf('/%s(\d+)/i', $this->get_option('syntaxPrefix')), $transaction->content, $matches)) {
                return;
            }

            $orderId = preg_replace('#(\D+)#', '', $matches[1]);
            $order = wc_get_order($orderId);

            if (! $order) {
                wp_send_json([
                    'success' => false,
                    'message' => 'No order found !',
                ]);
            }

            if ($order->has_status(['completed', 'over-paid'])) {
                wp_send_json([
                    'success' => false,
                    'message' => 'Order was paid !',
                ]);
            }

            $money = $order->get_total();
            $paid = $transaction->money;

            if ($paid < $money) {
                $order->add_order_note('Số tiền thanh toán không đủ với giá trị đơn hàng !');
                $order->update_status($this->get_option('orderStatusAfterLessPaid'), 'Pay via '.$this->id);

                wp_send_json([
                    'success' => false,
                    'message' => 'Not enough money !',
                ]);
            } else {
                $order->payment_complete();

                // Đơn hàng sẽ tự động trừ stock nếu thuộc wc-processing hoặc wc-completed
                if (! in_array($this->get_option('orderStatusAfterPaid'), ['wc-processing', 'wc-completed', 'wc-over-paid'])) {
                    wc_reduce_stock_levels($order);
                }

                if ($paid > $money) {
                    $order->update_status($this->get_option('orderStatusAfterOverPaid'), 'Pay via '.$this->id);
                } else {
                    $order->update_status($this->get_option('orderStatusAfterPaid'), 'Pay via '.$this->id);
                }

                wp_send_json([
                    'success' => true,
                    'message' => 'Order paid successfully',
                ]);
            }
        }
    }

    private function getHeader($header)
    {
        foreach ($_SERVER as $name => $value) {
            if ((str_starts_with($name, 'HTTP_')) && str_replace(' ', '-', ucwords(str_replace('_', ' ', substr($name, 5)))) === $header) {
                return $value;
            }
        }

        return false;
    }

    public function generate_bankAccounts_html()
    {
        ob_start(); ?>
        <tr>
            <th scope="row" class="titledesc"><?php esc_html_e('Tài khoản ngân hàng:', 'woocommerce'); ?></th>
            <td class="forminp" id="bacs_accounts">
                <div class="wc_input_table_wrapper">
                    <table class="widefat wc_input_table sortable" cellspacing="0">
                        <thead>
                        <tr>
                            <th class="sort">&nbsp;</th>
                            <th><?php esc_html_e('Tên tài khoản', 'woocommerce'); ?></th>
                            <th><?php esc_html_e('Số tài khoản', 'woocommerce'); ?></th>
                            <th><?php esc_html_e('Ngân hàng', 'woocommerce'); ?></th>
                            <th><?php esc_html_e('Chi nhánh', 'woocommerce'); ?></th>
                            <th><?php esc_html_e('VietQR.co Bank Code', 'woocommerce'); ?></th>
                            <th><?php esc_html_e('Logo', 'woocommerce'); ?></th>
                        </tr>
                        </thead>
                        <tbody class="accounts">
                        <?php
                        $i = -1;
        if ($this->bankAccounts) {
            foreach ($this->bankAccounts as $account) {
                $i++;

                echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="'.esc_attr(wp_unslash($account['account_name'])).'" name="bacs_account_name['.esc_attr($i).']" /></td>
										<td><input type="text" value="'.esc_attr($account['account_number']).'" name="bacs_account_number['.esc_attr($i).']" /></td>
										<td><input type="text" value="'.esc_attr(wp_unslash($account['bank_name'])).'" name="bacs_bank_name['.esc_attr($i).']" /></td>
										<td><input type="text" value="'.esc_attr(wp_unslash($account['bank_branch'])).'" name="bacs_bank_branch['.esc_attr($i).']" /></td>
										<td><input type="text" value="'.esc_attr(wp_unslash($account['bank_id'])).'" name="bacs_bank_id['.esc_attr($i).']" /></td>
										<td><input type="text" value="'.esc_attr(wp_unslash($account['bank_logo'])).'" name="bacs_bank_logo['.esc_attr($i).']" placeholder="tênngânhàng.png"/></td>
										
									</tr>';
            }
        } ?>
                        </tbody>
                        <tfoot>
                        <tr>
                            <th colspan="7">
                                <a href="#" class="add button"><?php esc_html_e('+ Thêm tài khoản', 'woocommerce'); ?></a>
                                <a href="#" class="remove_rows button"><?php esc_html_e('Xóa tài khoản', 'woocommerce'); ?></a>
                            </th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
                <script type="text/javascript">
                    jQuery(function () {
                        jQuery('#bacs_accounts').on('click', 'a.add', function () {

                            var size = jQuery('#bacs_accounts').find('tbody .account').length;

                            jQuery('<tr class="account">\
									<td class="sort"></td>\
									<td><input type="text" name="bacs_account_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_account_number[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_name[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_branch[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_id[' + size + ']" /></td>\
									<td><input type="text" name="bacs_bank_logo[' + size + ']" value="bank.svg"/></td>\
								</tr>').appendTo('#bacs_accounts table tbody');

                            return false;
                        });
                    });
                </script>
            </td>
        </tr>
        <tr>
            <td>
            </td>
            <td>
                Để biết dữ liệu cho cột VietQR.co Bank Code, vui lòng tham khảo thêm: <a href="https://vietqr.co/banks" title="xem định danh ngân hàng" target="_blank">https://vietqr.co/banks</a>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    public function updateBankAccounts()
    {
        $accounts = [];

        if (isset($_POST['bacs_account_name'], $_POST['bacs_account_number'], $_POST['bacs_bank_name'], $_POST['bacs_bank_logo'])) {
            $account_names = wc_clean(wp_unslash($_POST['bacs_account_name']));
            $account_numbers = wc_clean(wp_unslash($_POST['bacs_account_number']));
            $bank_names = wc_clean(wp_unslash($_POST['bacs_bank_name']));
            $bank_branches = wc_clean(wp_unslash($_POST['bacs_bank_branch']));
            $bank_ids = wc_clean(wp_unslash($_POST['bacs_bank_id']));
            $bank_logos = wc_clean(wp_unslash($_POST['bacs_bank_logo']));

            foreach ($account_names as $i => $name) {
                if (! isset($name)) {
                    continue;
                }

                $accounts[] = [
                    'account_name' => $name,
                    'account_number' => $account_numbers[$i],
                    'bank_name' => $bank_names[$i],
                    'bank_branch' => $bank_branches[$i],
                    'bank_id' => $bank_ids[$i],
                    'bank_logo' => $bank_logos[$i],
                ];
            }
        }

        update_option('woocommerce_bacs_accounts', $accounts);
    }

    public function showThankyouPage($orderId)
    {
        $order = wc_get_order($orderId);

        echo $this->thankyouPage('', $order);
    }

    public function thankyouPage($orderText = null, $order = null)
    {
        if ($order && $this->id === $order->get_payment_method()) {
            $gatewayContent = $order->is_paid() ? $this->receivedOrderTextAfterPaid : $this->bankLists($order);

            return $this->get_option('overrideThankyouHook') === 'yes' ? $orderText.$gatewayContent : $gatewayContent;
        }

        return $orderText;
    }

    protected function bankLists($order)
    {
        if (empty($this->bankAccounts)) {
            return '';
        }

        $bankAccounts = apply_filters('woocommerce_bacs_accounts', $this->bankAccounts, $order->get_id());

        $options = [
            'assets' => plugin_dir_url(__DIR__),
            'bankAccounts' => $bankAccounts,
            'header' => $this->receivedOrderTextBeforePaid,
            'footer' => $this->transferGuide,
            'order' => $this->get_option('syntaxPrefix').$order->get_id(),
            'amount' => number_format($order->get_total(), 0, ',', '.'),
            'endpoint' => site_url('/wc-api/'.$this->id),
            'showTextGuide' => $this->get_option('showTextGuide'),
            'guideText' => $this->get_option('guideText'),
            'guideLink' => $this->get_option('guideLink'),
            'vietQrStyle' => (int) $this->get_option('vietQrStyle'),
            'vietQrStyleLogo' => $this->get_option('vietQrStyleLogo') === 'yes' ? 0 : 1,
        ];

        return sprintf('<section id="thueapi"><banks :gateway="%s"/></section>', htmlentities(json_encode($options)));
    }

    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order->get_total() > 0) {
            $order->update_status(apply_filters('woocommerce_bacs_process_payment_order_status', 'on-hold', $order), __('Awaiting BACS payment', 'woocommerce'));
        } else {
            $order->payment_complete();
        }

        WC()->cart->empty_cart();

        return [
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        ];
    }

    public function woocommerceOrderIsPaidStatuses($statuses)
    {
        $exceptStatuses = array_map(static function ($status) {
            return str_replace('wc-', '', $status);
        }, (array) $this->get_option('forceShowPaymentWhenOrderStatuses'));

        if (! $exceptStatuses) {
            return $statuses;
        }

        foreach ($exceptStatuses as $exceptStatus) {
            $statuses = array_filter($statuses, static function ($status) use ($exceptStatus) {
                return $status !== $exceptStatus;
            });
        }

        return $statuses;
    }
}
