<?php
require_once(__DIR__ . '/interact.php');
require_once(__DIR__ . '/wp_util/user_meta.php');

class DoloresPosts {
  const taxonomy = 'tema';
  const type = 'ideia';

  public static function add_new_post($title, $text, $cat, $tags) {
    if (!is_user_logged_in()) {
      return array('error' => 'Apenas usuários cadastrados podem fazer isto.');
    }

    $user = wp_get_current_user();

    $title = trim($title);
    if (strlen($title) < 10 || strlen($title) > 100) {
      return array('error' => 'O título deve ter entre 10 e 100 caracteres.');
    }
    $title = str_replace('<', '&lt;', $title);
    $title = str_replace('>', '&gt;', $title);

    $text = trim($text);
    if (strlen($text) > 600) {
      return array('error' => 'O texto deve ter no máximo 600 caracteres.');
    }
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);

    $post = array(
      'post_title' => $title,
      'post_content' => $text,
      'post_status' => 'publish',
      'post_type' => DoloresPosts::type,
      'post_author' => $user->ID,
      'ping_status' => 'closed'
    );

    $inserted = wp_insert_post($post);
    if (!$inserted) {
      return array('error' => 'Erro ao cadastrar ideia.');
    }

    if (!is_array($tags)) {
      $tags = array();
    }
    $terms = array_merge(array($cat), $tags);

    // TODO: validate terms

    wp_set_object_terms($inserted, $terms, DoloresPosts::taxonomy);

    return array('url' => get_permalink($inserted));
  }

  public static function add_new_comment($text, $post_id, $parent = 0) {
    global $_SERVER;

    if (!is_user_logged_in()) {
      return array('error' => 'Apenas usuários cadastrados podem fazer isto.');
    }

    $user = wp_get_current_user();

    if (!comments_open($post_id)) {
      return array('error' => 'Esta ideia não aceita comentários.');
    }

    if ($parent) {
      $parent_comment = get_comment($parent);
      if ($parent_comment->comment_parent) {
        return array('error' => 'Suportamos apenas 2 níveis de respostas.');
      }
    }

    $text = trim($text);
    if (strlen($text) > 600) {
      return array('error' => 'O texto deve ter no máximo 600 caracteres.');
    }
    $text = str_replace('<', '&lt;', $text);
    $text = str_replace('>', '&gt;', $text);
    $text = nl2br($text);

    $comment = array(
      'user_id' => $user->ID,
      'comment_author' => $user->display_name,
      'comment_author_email' => $user->user_email,
      'comment_author_IP' => $_SERVER['REMOTE_ADDR'],
      'comment_agent' => $_SERVER['HTTP_USER_AGENT'],
      'comment_post_ID' => $post_id,
      'comment_parent' => $parent,
      'comment_content' => $text
    );

    $inserted = wp_insert_comment($comment);
    if (!$inserted) {
      return array('error' => 'Erro ao cadastrar comentário.');
    }

    return array("html" => static::get_comment_html(get_comment($inserted)));
  }

  public static function get_comment_html($comment) {
    $interact = DoloresInteract::get_instance();
    list($up, $down, $voted) =
      $interact->get_comment_votes($comment->comment_ID);
    $data = "href=\"#vote\" data-vote=\"comment_id|{$comment->comment_ID}\"";
    $upvoted = $downvoted = "";
    if ($voted === "up") {
      $upvoted = " voted";
    } else if ($voted === "down") {
      $downvoted = " voted";
    }

    $user = get_user_by('id', $comment->user_id);
    $picture = dolores_get_profile_picture($user);
    $style = ' style="background-image: url(\'' . $picture. '\');"';
    $url = get_author_posts_url($comment->user_id);
    $format = get_option('date_format') . ' à\s ' . get_option('time_format');
    $datetime = get_comment_date($format, $comment->comment_ID);
    if (!$comment->comment_parent) {
      $reply = <<<HTML
<a class="ideia-comment-action ideia-comment-reply" href="#reply">
  <i class="fa fa-fw fa-lg fa-comments"></i> Responder
</a>
HTML;
    } else {
      $reply = "";
    }
    $content = <<<HTML
<li class="ideia-comment" id="comment-{$comment->comment_ID}">
  <div class="ideia-comment-table">
    <a href="{$url}" class="ideia-comment-picture">
      <span class="grid-ideia-author-picture" {$style}>
      </span>
    </a>
    <div class="ideia-comment-block">
      <div class="ideia-comment-text">
        <span class="ideia-comment-author">
          <a href="{$url}">
            {$user->display_name}
          </a>
        </span>
        <span class="ideia-comment-content">
          {$comment->comment_content}
        </span>
      </div>
      <div class="ideia-comment-meta">
        <a class="ideia-comment-action ideia-upvote{$upvoted}" {$data}>
          <i class="fa fa-fw fa-lg fa-thumbs-up"></i>
          <span class="number">{$up}</span>
        </a>
        <a class="ideia-comment-action ideia-downvote{$downvoted}" {$data}>
          <i class="fa fa-fw fa-lg fa-thumbs-down"></i>
          <span class="number">{$down}</span>
        </a>
        {$reply}
        <span class="ideia-comment-date">
          {$datetime}
        </span>
      </div>
    </div>
  </div>
HTML;
    return $content;
  }
};
