<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
global $product;
?>

<div class="wc-jpm-price-breakdown">
    <h4><?php _e('Price Breakdown', 'wc-jpm'); ?></h4>
    
    <?php if (!empty($breakdown)) : ?>
        <table class="shop_table wc-jpm-breakdown-table">
            <thead>
                <tr>
                    <th><?php _e('Material', 'wc-jpm'); ?></th>
                    <th><?php _e('Weight', 'wc-jpm'); ?></th>
                    <th><?php _e('Purity', 'wc-jpm'); ?></th>
                    <th><?php _e('Cost', 'wc-jpm'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($breakdown as $item) : ?>
                    <tr>
                        <td><?php echo esc_html($item['material']); ?></td>
                        <td><?php echo esc_html($item['weight'] . ' ' . $item['unit']); ?></td>
                        <td><?php echo esc_html($item['purity']); ?></td>
                        <td><?php echo wc_price($item['cost']); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($labor_cost > 0) : ?>
                    <tr>
                        <td colspan="3"><?php _e('Labor Cost', 'wc-jpm'); ?></td>
                        <td><?php echo wc_price($labor_cost); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="3"><?php _e('Total', 'wc-jpm'); ?></th>
                    <th><?php echo wc_price($final_price); ?></th>
                </tr>
            </tfoot>
        </table>

        <canvas id="wc-jpm-price-chart" width="400" height="200"></canvas>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('wc-jpm-price-chart').getContext('2d');
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: [
                        <?php
                        $labels = array_map(function($item) { return "'{$item['material']}'"; }, $breakdown);
                        if ($labor_cost > 0) $labels[] = "'Labor Cost'";
                        echo implode(',', $labels);
                        ?>
                    ],
                    datasets: [{
                        data: [
                            <?php
                            $data = array_map(function($item) { return $item['cost']; }, $breakdown);
                            if ($labor_cost > 0) $data[] = $labor_cost;
                            echo implode(',', $data);
                            ?>
                        ],
                        backgroundColor: [
                            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.label + ': ' + '<?php echo get_woocommerce_currency_symbol(); ?>' + context.raw.toFixed(2);
                                }
                            }
                        }
                    }
                }
            });
        });
        </script>
    <?php else : ?>
        <p><?php _e('No breakdown available for this product.', 'wc-jpm'); ?></p>
    <?php endif; ?>
</div>