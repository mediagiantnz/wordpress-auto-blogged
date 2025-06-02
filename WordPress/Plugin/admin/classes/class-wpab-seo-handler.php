<?php
// File: wp-auto-blogger/admin/classes/class-wpab-seo-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_SEO_Handler {
    /**
     * Set Yoast SEO meta fields for a post.
     *
     * @param int    $post_id       The ID of the post.
     * @param array  $topic         The topic data.
     * @param string $content       The post content.
     * @param array  $category_ids  The categories assigned to the post.
     */
    public function set_yoast_seo_meta( $post_id, $topic, $content, $category_ids ) {
        // Generate SEO title and description based on the content.
        $seo_title = $this->generate_seo_title( $topic['title'] );
        $seo_description = $this->generate_seo_description( $content );
        $focus_keyphrase = $this->generate_focus_keyphrase( $category_ids );

        // Update Yoast SEO meta fields.
        update_post_meta( $post_id, '_yoast_wpseo_title', $seo_title );
        update_post_meta( $post_id, '_yoast_wpseo_metadesc', $seo_description );
        update_post_meta( $post_id, '_yoast_wpseo_focuskw', $focus_keyphrase );
    }

    /**
     * Generate an SEO-friendly title.
     *
     * @param string $title The original title.
     * @return string       The SEO-optimized title.
     */
    private function generate_seo_title( $title ) {
        // Convert to title case.
        $seo_title = mb_convert_case( $title, MB_CASE_TITLE, "UTF-8" );
        // Limit title to 75 characters including spaces.
        if ( mb_strlen( $seo_title ) > 75 ) {
            $seo_title = mb_substr( $seo_title, 0, 75 );
        }
        return $seo_title;
    }

    /**
     * Generate an SEO-friendly meta description.
     *
     * @param string $content The post content.
     * @return string          The SEO-optimized meta description.
     */
    private function generate_seo_description( $content ) {
        // Strip markdown and HTML tags.
        $content_text = strip_tags( $this->markdown_to_plain_text( $content ) );
        // Extract up to 164 characters including spaces.
        $seo_description = mb_substr( $content_text, 0, 164 );
        $seo_description = rtrim( $seo_description, "!,.-" );
        // Ensure it doesn't cut off mid-word.
        if ( mb_strlen( $seo_description ) > 0 ) {
            $last_space = mb_strrpos( $seo_description, ' ' );
            if ( $last_space !== false ) {
                $seo_description = mb_substr( $seo_description, 0, $last_space );
            }
        }
        // Wrap in <p> tags.
        $seo_description = '<p>' . esc_html( $seo_description ) . '</p>';
        return $seo_description;
    }

    /**
     * Generate focus keyphrase based on the categories.
     *
     * @param array $category_ids The category IDs assigned to the post.
     * @return string             The focus keyphrase.
     */
    private function generate_focus_keyphrase( $category_ids ) {
        // Get the names of the categories.
        $category_names = array();
        foreach ( $category_ids as $category_id ) {
            $category = get_category( $category_id );
            if ( $category && ! is_wp_error( $category ) ) {
                $category_names[] = $category->name;
            }
        }
        // Combine category names into a focus keyphrase.
        $focus_keyphrase = implode( ', ', $category_names );
        return $focus_keyphrase;
    }

    /**
     * Convert markdown to plain text.
     *
     * @param string $markdown The markdown content.
     * @return string          The plain text.
     */
    private function markdown_to_plain_text( $markdown ) {
        // Remove markdown formatting.
        $plain_text = preg_replace( '/\!\[.*?\]\(.*?\)/', '', $markdown ); // Remove images.
        $plain_text = preg_replace( '/\[([^\]]+)\]\([^\)]+\)/', '$1', $plain_text ); // Remove links but keep text.
        $plain_text = preg_replace( '/\*\*(.*?)\*\*/', '$1', $plain_text ); // Bold.
        $plain_text = preg_replace( '/\*(.*?)\*/', '$1', $plain_text ); // Italic.
        $plain_text = preg_replace( '/\#\#\# (.*?)(\n|$)/', '$1', $plain_text ); // H3.
        $plain_text = preg_replace( '/\#\# (.*?)(\n|$)/', '$1', $plain_text ); // H2.
        $plain_text = preg_replace( '/\# (.*?)(\n|$)/', '$1', $plain_text ); // H1.
        $plain_text = strip_tags( $plain_text );
        return $plain_text;
    }
}
