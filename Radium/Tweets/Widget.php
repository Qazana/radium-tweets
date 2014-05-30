<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/** Load all of the necessary class files for the plugin */
//spl_autoload_register( 'Radium_Forms::autoload' );

/**
 * Init class for Radium_Tweets.
 *
 * Loads all of the necessary components for the radium tweets plugin.
 *
 * @since 1.0.0
 *
 * @package Radium_Tweets
 * @author  Franklin Gitonga
 */

class Radium_Tweets_Widget extends WP_Widget {

     public $options;

    /*--------------------------------------------------------------------*/
    /*  WIDGET SETUP
    /*--------------------------------------------------------------------*/
    public function __construct() {
        parent::__construct(
            'Radium_tweets', // BASE ID
            'Radium Tweets', // NAME
            array( 'description' => __( 'A widget that displays your most recent tweets with API v1.1', 'radium' ), )
        );

        $this->options = get_option( 'radium_tweets_settings' );

    }


    /*--------------------------------------------------------------------*/
    /*  DISPLAY WIDGET
    /*--------------------------------------------------------------------*/
    public function widget($args, $instance) {
        extract($args);
        if(!empty($instance['title'])){ $title = apply_filters( 'widget_title', $instance['title'] ); }

        $options = $this->options;

        echo $before_widget;

        if ( ! empty( $title ) ){ echo $before_title . $title . $after_title; }

                // CHECK SETTINGS & DIE IF NOT SET
                if(empty($options['consumerkey']) || empty($options['consumersecret']) || empty($options['accesstoken']) || empty($options['accesstokensecret']) || empty($options['cachetime']) || empty($options['username'])){
                    echo '<div class="alert info"><strong>Please fill all widget settings!</strong></div>' . $after_widget;
                    return;
                }

                // CHECK IF CACHE NEEDS UPDATE
                $radium_twitter_plugin_last_cache_time = get_option('radium_twitter_plugin_last_cache_time');
                $diff = time() - $radium_twitter_plugin_last_cache_time;
                $crt = $options['cachetime'] * 3600;

                //  YUP, NEEDS ONE
                if($diff >= $crt || empty($radium_twitter_plugin_last_cache_time)){

                    $connection = Radium_Tweets_Functions::getConnectionWithAccessToken($options['consumerkey'], $options['consumersecret'], $options['accesstoken'], $options['accesstokensecret']);
                    $tweets = $connection->get("https://api.twitter.com/1.1/statuses/user_timeline.json?screen_name=".$options['username']."&count=10");

                    if( !$tweets ) {

                        echo ( '<div class="alert error">Couldn\'t retrieve tweets! Wrong username?</div>');

                        return;

                    }

                    if(!empty($tweets->errors)){

                        if($tweets->errors[0]->message == 'Invalid or expired token'){

                            echo '<div class="alert info"><strong>'.$tweets->errors[0]->message.'!</strong><br />You\'ll need to regenerate it <a href="https://dev.twitter.com/apps" target="_blank">here</a>!' . $after_widget;

                        } else {

                            echo '<div class="alert info"><strong>'.$tweets->errors[0]->message.'</strong></div>' . $after_widget;

                        }

                        return;
                    }

                    for($i = 0;$i <= count($tweets); $i++){

                        if(!empty($tweets[$i])){
                            $tweets_array[$i]['created_at'] = $tweets[$i]->created_at;
                            $tweets_array[$i]['text'] = $tweets[$i]->text;
                            $tweets_array[$i]['status_id'] = $tweets[$i]->id_str;
                        }

                    }

                    // SAVE TWEETS TO WP OPTION
                    update_option('radium_twitter_plugin_tweets',serialize($tweets_array));
                    update_option('radium_twitter_plugin_last_cache_time',time());

                    echo '<!-- twitter cache has been updated! -->';
                }


            $radium_twitter_plugin_tweets = maybe_unserialize(get_option('radium_twitter_plugin_tweets'));

            $id = rand(0,999);

            if(!empty($radium_twitter_plugin_tweets)){
                print '

                <div class="twitter-div">
                    <ul id="twitter-update-list-'.$id.'">';
                    $fctr = '1';
                    foreach($radium_twitter_plugin_tweets as $tweet){
                        print '<li><span>'.Radium_Tweets_Functions::convert_links($tweet['text']).'</span><a class="twitter-time" target="_blank" href="http://twitter.com/'.$instance['username'].'/statuses/'.$tweet['status_id'].'">'.Radium_Tweets_Functions::relative_time($tweet['created_at']).'</a></li>';
                        if($fctr == $instance['tweetstoshow']){ break; }
                        $fctr++;
                    }

                print '
                    </ul>';
                    if($instance['tweettext'] !='') : ?> <a href="http://twitter.com/<?php echo $options['username'] ?>" class="button" target="blank"><?php echo $instance['tweettext'] ?></a><?php endif;
            echo '</div>';

            }

        echo $after_widget;
    }


    /*--------------------------------------------------------------------*/
    /*  UPDATE WIDGET
    /*--------------------------------------------------------------------*/
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = strip_tags( $new_instance['title'] );
        $instance['tweetstoshow'] = strip_tags( $new_instance['tweetstoshow'] );
        $instance['tweettext'] = strip_tags( $new_instance['tweettext'] );

        return $instance;
    }


    /*--------------------------------------------------------------------*/
    /*  WIDGET SETTINGS (FRONT END PANEL)
    /*--------------------------------------------------------------------*/
    public function form($instance) {
        $defaults = array( 'title' => 'Radium Tweets Plugin', 'consumerkey' => '', 'consumersecret' => '', 'accesstoken' => '', 'accesstokensecret' => '', 'cachetime' => '2', 'username' => '', 'tweetstoshow' => '', 'tweettext' => '', );
        $instance = wp_parse_args( (array) $instance, $defaults );

        echo '
        <p><label>Title:</label>
            <input type="text" name="'.$this->get_field_name( 'title' ).'" id="'.$this->get_field_id( 'title' ).'" value="'.esc_attr($instance['title']).'" class="widefat" /></p>
        <p style="margin-bottom: 15px;"><label>Number of Tweets:</label>
            <select type="text" name="'.$this->get_field_name( 'tweetstoshow' ).'" id="'.$this->get_field_id( 'tweetstoshow' ).'">';
            $i = 1;
            for(i; $i <= 10; $i++){
                echo '<option value="'.$i.'"'; if($instance['tweetstoshow'] == $i){ echo ' selected="selected"'; } echo '>'.$i.'</option>';
            }
            echo '
            </select>
        </p>
        <p><label>Button Text</label>
        <input type="text" name="'.$this->get_field_name( 'tweettext' ).'" id="'.$this->get_field_id( 'tweettext' ).'" value="'.esc_attr($instance['tweettext']).'" class="widefat" /></p>';
    }

}
