<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="wrap wc-jpm-admin-section">
    <h2><?php _e('Rate Analytics', 'wc-jpm'); ?></h2>
    <p><?php _e('View historical rate trends for jewelry materials over the past 30 days.', 'wc-jpm'); ?></p>

    <?php
    $analytics = new WC_JPM_Analytics();
    $materials = $this->db->get_materials(); // $this->db is set in WC_JPM_Admin
    ?>

    <?php foreach ($materials as $material) : ?>
        <h3><?php echo esc_html(ucfirst($material['name'])); ?></h3>
        <div class="wc-jpm-analytics-chart">
            <canvas id="chart-<?php echo esc_attr($material['id']); ?>" width="600" height="400"></canvas>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const ctx<?php echo $material['id']; ?> = document.getElementById('chart-<?php echo esc_attr($material['id']); ?>').getContext('2d');
                    new Chart(ctx<?php echo $material['id']; ?>, {
                        type: 'line',
                        data: {
                            labels: <?php echo json_encode(array_column($analytics->get_rate_trends($material['id']), 'recorded_at')); ?>,
                            datasets: [{
                                label: '<?php echo esc_js(ucfirst($material['name'])); ?> Rate (<?php echo esc_js(get_woocommerce_currency()); ?>)',
                                data: <?php echo json_encode(array_column($analytics->get_rate_trends($material['id']), 'rate')); ?>,
                                borderColor: '#0073aa',
                                backgroundColor: 'rgba(0, 115, 170, 0.1)',
                                fill: true,
                                tension: 0.1
                            }]
                        },
                        options: {
                            responsive: true,
                            scales: {
                                x: {
                                    title: { display: true, text: '<?php _e('Date', 'wc-jpm'); ?>' }
                                },
                                y: {
                                    beginAtZero: false,
                                    title: { display: true, text: '<?php _e('Rate', 'wc-jpm'); ?>' }
                                }
                            },
                            plugins: {
                                legend: { display: true },
                                tooltip: { mode: 'index', intersect: false }
                            }
                        }
                    });
                });
            </script>
        </div>
    <?php endforeach; ?>

    <?php if (empty($materials)) : ?>
        <p><?php _e('No materials found. Add materials in the plugin settings to view analytics.', 'wc-jpm'); ?></p>
    <?php endif; ?>
</div>