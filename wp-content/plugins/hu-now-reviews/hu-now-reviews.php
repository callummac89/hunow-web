<?php
/**
 * Plugin Name: HU NOW Reviews (MVP)
 * Description: Native UGC reviews with shortcodes: [hu_rating_summary], [hu_reviews], [hu_review_form].
 * Version: 1.0.1
 * Author: HU NOW
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('HU_Reviews_MVP')) {

class HU_Reviews_MVP {
    const TYPE = 'hunow_review';
    const NONCE = 'hu_review_submit';

    function __construct() {
        // Original shortcodes
        add_shortcode('hu_rating_summary', array($this,'sc_summary'));
        add_shortcode('hu_reviews',         array($this,'sc_reviews'));
        add_shortcode('hu_review_form',     array($this,'sc_form'));

        // Aliases for backwards compatibility / different naming
        add_shortcode('hunow_rating',       array($this,'sc_summary'));
        add_shortcode('hunow_reviews',      array($this,'sc_reviews'));
        add_shortcode('hunow_leave_review', array($this,'sc_form'));

        add_action('init',                  array($this,'handle_form'));
        add_action('wp_head',               array($this,'schema'));

        add_action('wp_enqueue_scripts', array($this,'enqueue_styles'));
    }

    function enqueue_styles() {
        $css = '.hu-stars{display:inline-flex;gap:4px}.hu-star{width:20px;height:20px;mask:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 24 24\'><path d=\'M12 17.3l6.18 3.73-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.76L5.82 21z\'/></svg>") center/contain no-repeat;background:#f5c518}.hu-star--empty{background:#e5e7eb}.hu-summary{display:flex;align-items:center;gap:10px;margin:.5rem 0}.hu-summary b{font-size:14px}.hu-review{border:1px solid #eee;border-radius:12px;padding:16px;margin:14px 0;background:#fff}.hu-review header{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.hu-review h4{margin:0;font-size:16px}.hu-meta{color:#6b7280;font-size:13px}.hu-form{border:1px solid #eee;border-radius:12px;padding:16px;background:#fff;margin-top:12px}.hu-form input,.hu-form textarea{width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:8px}.hu-form label{display:block;margin:6px 0 6px;font-weight:600}.hu-form .row{display:grid;gap:12px;grid-template-columns:1fr 1fr}.hu-btn{display:inline-block;background:#111827;color:#fff;border:0;border-radius:999px;padding:12px 18px;cursor:pointer}.hu-rate{display:inline-flex;flex-direction:row-reverse;gap:4px}.hu-rate input{display:none}.hu-rate label{width:24px;height:24px;cursor:pointer;filter:grayscale(1);background:url("data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' fill=\'%23f5c518\' viewBox=\'0 0 24 24\'><path d=\'M12 17.3l6.18 3.73-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.76L5.82 21z\'/></svg>") center/contain no-repeat}.hu-rate input:checked ~ label,.hu-rate label:hover,.hu-rate label:hover ~ label{filter:none}.hu-reviews-wrap{margin-top:18px}.hu-pagination{margin-top:10px}.hu-pagination a{margin-right:6px}.hu-pagination strong{margin-right:6px}';
        // Attach to a core handle that exists on front-end
        wp_register_style('hu-now-reviews-inline', false);
        wp_enqueue_style('hu-now-reviews-inline');
        wp_add_inline_style('hu-now-reviews-inline', $css);
    }

    /* ------------ Helpers ------------ */
    static function stars($val){
        $val = max(0, min(5, floatval($val)));
        $o = '<span class="hu-stars" aria-label="'.esc_attr($val).' out of 5">';
        for ($i=1;$i<=5;$i++){
            $cls = ($val >= $i) ? '' : ' hu-star--empty';
            $o .= '<i class="hu-star'.$cls.'" aria-hidden="true"></i>';
        }
        return $o.'</span>';
    }
    static function stats($post_id){
        $cmts = get_comments(array('post_id'=>$post_id,'status'=>'approve','type'=>self::TYPE));
        $sum=0;$n=0;
        foreach($cmts as $c){ $r=intval(get_comment_meta($c->comment_ID,'hu_rating',true)); if($r){$sum+=$r;$n++;}}
        return array('count'=>$n,'avg'=>$n?round($sum/$n,1):0);
    }

    /* ------------ Shortcodes ------------ */
    function sc_summary($atts){
        $a = shortcode_atts(array('post_id'=>get_the_ID(),'label'=>''), $atts);
        $s = self::stats($a['post_id']);
        $html  = '<div class="hu-summary">'.self::stars($s['avg']);
        $html .= '<b>'.esc_html($s['avg']).' / 5 · '.esc_html($s['count']).' review'.($s['count']!=1?'s':'').'</b>';
        if ($a['label']) $html .= '<span>'.esc_html($a['label']).'</span>';
        return $html.'</div>';
    }

    function sc_reviews($atts){
        $a = shortcode_atts(array('post_id'=>get_the_ID(),'per_page'=>6), $atts);
        $paged = max(1, absint(isset($_GET['hu_paged']) ? $_GET['hu_paged'] : 1));
        $reviews = get_comments(array(
            'post_id'=>$a['post_id'],'status'=>'approve','type'=>self::TYPE,
            'number'=>$a['per_page'],'paged'=>$paged,'orderby'=>'comment_date_gmt','order'=>'DESC'
        ));
        if(!$reviews) return '<p>No reviews yet. Be the first!</p>';

        ob_start();
        echo '<div class="hu-reviews-wrap">';
        foreach($reviews as $c){
            $r=intval(get_comment_meta($c->comment_ID,'hu_rating',true));
            $t=(string)get_comment_meta($c->comment_ID,'hu_title',true);
            echo '<article class="hu-review">';
            echo '<header>'.self::stars($r).'<h4>'.($t ? esc_html($t) : 'Review').'</h4>';
            echo '<div class="hu-meta"><b>'.esc_html($c->comment_author).'</b><span> · '.esc_html(get_date_from_gmt($c->comment_date_gmt, get_option('date_format'))).'</span></div></header>';
            echo '<div>'.wpautop(esc_html($c->comment_content)).'</div>';
            echo '</article>';
        }
        // simple pager
        $total = get_comments(array('post_id'=>$a['post_id'],'status'=>'approve','type'=>self::TYPE,'count'=>true));
        $max = (int)ceil($total/$a['per_page']);
        if($max>1){
            echo '<p class="hu-pagination">';
            for($i=1;$i<=$max;$i++){
                $url = esc_url(add_query_arg('hu_paged',$i));
                echo $i===$paged ? "<strong>$i</strong> " : "<a href='$url'>$i</a> ";
            }
            echo '</p>';
        }
        echo '</div>';
        return ob_get_clean();
    }

    function sc_form($atts){
        if (!is_singular()) return '';
        $post_id = get_the_ID();
        ob_start(); ?>
        <form class="hu-form" method="post" action="#hu-reviews">
            <?php wp_nonce_field(self::NONCE); ?>
            <input type="hidden" name="post_id" value="<?php echo esc_attr($post_id); ?>">
            <input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off"><!-- honeypot -->

            <div>
                <label><?php _e('Your rating'); ?></label>
                <div class="hu-rate">
                    <?php for($i=5;$i>=1;$i--): ?>
                        <input type="radio" id="hu-r<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>">
                        <label for="hu-r<?php echo $i; ?>" title="<?php echo $i; ?> stars"></label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="row">
                <div>
                    <label><?php _e('Your name'); ?></label>
                    <input type="text" name="author" required>
                </div>
                <div>
                    <label><?php _e('Email (won’t be published)'); ?></label>
                    <input type="email" name="email" required>
                </div>
            </div>

            <div>
                <label><?php _e('Title (optional)'); ?></label>
                <input type="text" name="title" placeholder="e.g., Brilliant Sunday roast">
            </div>

            <div>
                <label><?php _e('Your review'); ?></label>
                <textarea name="content" rows="6" required></textarea>
            </div>

            <button class="hu-btn" type="submit" name="hu_review_submit" value="1"><?php _e('Submit review'); ?></button>

            <?php if (isset($_GET['hu_review']) && $_GET['hu_review']==='thanks'): ?>
                <p style="color:#065f46;margin:8px 0 0"><?php _e('Thanks! Your review is awaiting moderation.'); ?></p>
            <?php elseif (isset($_GET['hu_review']) && $_GET['hu_review']==='invalid'): ?>
                <p style="color:#991b1b;margin:8px 0 0"><?php _e('Please complete all required fields.'); ?></p>
            <?php endif; ?>
        </form>
        <?php
        return ob_get_clean();
    }

    /* ------------ Handle form ------------ */
    function handle_form(){
        if (!isset($_POST['hu_review_submit'])) return;
        $post_id = absint(isset($_POST['post_id']) ? $_POST['post_id'] : 0);
        if(!$post_id) return;
        check_admin_referer(self::NONCE);
        if (!empty($_POST['website'])) return; // honeypot

        $author  = trim(wp_strip_all_tags(isset($_POST['author']) ? $_POST['author'] : ''));
        $email   = trim(sanitize_email(isset($_POST['email']) ? $_POST['email'] : ''));
        $title   = trim(wp_strip_all_tags(isset($_POST['title']) ? $_POST['title'] : ''));
        $content = trim(wp_kses_post(isset($_POST['content']) ? $_POST['content'] : ''));
        $rating  = (int) (isset($_POST['rating']) ? $_POST['rating'] : 0);

        if (!$author || !is_email($email) || !$content || $rating<1 || $rating>5) {
            wp_safe_redirect(add_query_arg('hu_review','invalid', get_permalink($post_id)));
            exit;
        }

        $cid = wp_insert_comment(array(
            'comment_post_ID'      => $post_id,
            'comment_author'       => $author,
            'comment_author_email' => $email,
            'comment_content'      => $content,
            'comment_type'         => self::TYPE,
            'comment_approved'     => 0,
        ));
        if ($cid) {
            update_comment_meta($cid,'hu_rating',$rating);
            if ($title) update_comment_meta($cid,'hu_title',$title);
            wp_safe_redirect(add_query_arg('hu_review','thanks', get_permalink($post_id).'#hu-reviews'));
            exit;
        }
        wp_safe_redirect(add_query_arg('hu_review','invalid', get_permalink($post_id)));
        exit;
    }

    /* ------------ JSON-LD ------------ */
    function schema(){
        if (!is_singular()) return;
        $post_id = get_the_ID();
        $s = self::stats($post_id);
        if (!$s['count']) return;

        $type = 'LocalBusiness';
        $pt = get_post_type($post_id);
        if ($pt === 'event') $type = 'Event';
        if ($pt === 'guide') $type = 'CreativeWork';

        $data = array(
            '@context'=>'https://schema.org',
            '@type'=>$type,
            'name'=>get_the_title($post_id),
            'url'=>get_permalink($post_id),
            'aggregateRating'=>array(
                '@type'=>'AggregateRating',
                'ratingValue'=>$s['avg'],
                'reviewCount'=>$s['count'],
                'bestRating'=>5,'worstRating'=>1
            )
        );
        echo '<script type="application/ld+json">'.wp_json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';
    }
}
new HU_Reviews_MVP();

} // end class_exists
