<?php
include_once $config['dir']['themes'] . '/modlog/info.php';




function modlog_build($action, $settings, $data)
{
	
	global $config, $mod;

	$mod_actions = 	['mod-delete','mod-ban', 'mod-ban-delete','mod-delete-by-ip'];

	if (!in_array($action, $mod_actions)) {
		return;
	}


	$post; 
	$postBoard = $data['board'];
	$postID = $data['post']; 

	if($postBoard == $settings['dboard']) {
		return;
	}

	if($action == 'mod-ban'){
		if(!openBoard($postBoard))
			return;
	}

	if ($settings['boards'] != '*' && !in_array($postBoard, explode(' ', $settings['boards']))) {
		return;
	}
	
	$thread_id = modlog_get_thread($settings['dboard'], $postBoard);




	$query = prepare(sprintf("SELECT * FROM `posts_%s` WHERE `id` = :id", $postBoard));
	$query->bindParam(':id', $postID, PDO::PARAM_INT);

	if ($query->execute()) {
		$post = $query->fetch();
			
		if($post) {

			$mod_fullaction =  $action . ($post['thread'] == null ? '-thread' : '-post');

			$old_modifiers = extract_modifiers($post['body_nomarkup']);
			$body = remove_modifiers($post['body_nomarkup']);

			$body .= "<tinyboard ml_user>{$mod['username']}</tinyboard>";
			$body .= "<tinyboard ml_action>{$mod_fullaction}</tinyboard>";
			$body .= "<tinyboard ml_post>{$data['post']}</tinyboard>";
			$body .= "<tinyboard ml_messsage>{$data['messsage']}</tinyboard>";
			$body .= "<tinyboard ml_reason>{$data['reason']}</tinyboard>";
			$body .= "<tinyboard ml_length>{$data['length']}</tinyboard>";

			$post['body_nomarkup'] = $body;
			$post['modifiers'] = extract_modifiers($post['body_nomarkup']);;
			
			modlog_insert($settings['dboard'], $thread_id, $message, $postBoard, $postID, array($post));
		}
	}	
	
}


function modlog_get_thread($modlog_board, $target_board)
{

	global $config, $board;


	$_board = $board;
	$thread = false;
	$subject = '/' . $target_board . ' modlog';
	$query = prepare(sprintf("SELECT * FROM `posts_%s` WHERE `subject` = :subj", $modlog_board));
	$query->bindParam(':subj', $subject, PDO::PARAM_STR);
	
	if(!$query->execute()) {
		return false;
	}

	$thread_id =false;
	$thread = $query->fetch();

	if(!$thread) {

		// create thread
		$post = array(
			'op'=> true,
			'mod'=>true,
			'board' => $modlog_board,
			'subject'=> $subject,
			'name'=> '',
			'trip'=> '',
			'email'=> '',
			'password'=> '', 
			'capcode'=> '', 
			'has_file'=> false, 
			'body'=> 'BOARD LOG', 
			'body_nomarkup'=>'BOARD LOG', 
			'files' => array(),
			'time'  => time(),
			'bump'  => time(),
			'ip' => '127.0.0.10',
			'sticky'=> false,
			'locked'=> false,
			'cycle'=> true,
		);

		$board['uri'] = $modlog_board;
		$template = '';
		$thread_id = post($post, $template);
		$board['uri'] = $_board['uri'];

	} else {
		$thread_id = $thread['id'];
	}

	return $thread_id;
}

function modlog_insert($modlog_board, $modlog_thread, $message, $target_board, $target_post, $posts) {

	global $mod, $board;

	$_board = $board;
	$board['uri'] = $modlog_board;

	foreach($posts as $post){

		$post['op'] = false;
		$post['mod'] = false;
		$post['has_file'] = $post['num_files'] != 0;

		if($post['has_file']) {
			$post['files'] = json_decode($post['files'], TRUE);

			// copying files
			foreach($post['files'] as $file) {

				$destFile  = str_replace($target_board . '/src', $modlog_board . '/src', $file['file_path']);
				$destThumb  = str_replace($target_board . '/th', $modlog_board . '/th', $file['thumb_path']);
 
				@copy($file['file_path'], $destFile);
				@copy($file['thumb_path'], $destThumb);
				
			}
		}
		$post['board'] = $target_board;
		$post['thread'] = $modlog_thread;
		$post['body'] = $message . "\n" . $post['body'];
		$post['body_nomarkup'] = $message .  "\n" . $post['body_nomarkup'];

		$template = '';
		$id = post($post, $template);
	}

	
	
	openBoard($modlog_board);
	buildThread($modlog_thread);
	buildIndex();
	openBoard($target_board);

	$board = $_board;
}
























