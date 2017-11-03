<?php

class LP_Question_CURD implements LP_Interface_CURD {
	public function delete( &$question ) {
		// TODO: Implement delete() method.
	}

	/**
	 * @param object $question
	 *
	 * @return object
	 */
	public function create( &$question ) {
//		return $question;
	}

	/**
	 * @param LP_Question $question
	 *
	 * @return mixed
	 */
	public function load( &$question ) {
		$the_id = $question->get_id();

		if ( ! $the_id || LP_QUESTION_CPT !== get_post_type( $the_id ) ) {
			LP_Debug::throw_exception( sprintf( __( 'Invalid question with ID "%d".', 'learnpress' ), $the_id ) );
		}
		$question->set_data_via_methods(
			array(
				'explanation' => get_post_meta( $the_id, '_lp_explanation', true ),
				'hint'        => get_post_meta( $the_id, '_lp_hint', true )
			)
		);
		$this->_load_answer_options( $question );
		$this->_load_meta( $question );

		return true;
	}

	/**
	 * Load question meta data.
	 *
	 * @param LP_Question $question
	 */
	protected function _load_meta( $question ) {
		$type = get_post_meta( $question->get_id(), '_lp_type', true );
		if ( ! learn_press_is_support_question_type( $type ) ) {
			$type = 'true_or_false';
		}
		$question->set_type( $type );

		$mark = $this->_get_question_mark( $question->get_id() );
		$question->set_data_via_methods(
			array(
				'mark' => $mark
			)
		);
	}

	public function _get_question_mark( $question_id ) {
		$mark = abs( get_post_meta( $question_id, '_lp_mark', true ) );
		if ( ! $mark ) {
			$mark = apply_filters( 'learn-press/question/default-mark', 1, $question_id );
			update_post_meta( $question_id, '_lp_mark', $mark );
		}

		return $mark;
	}

	/**
	 * Update question.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args
	 *
	 * @return array
	 */
	public function update( &$args = array() ) {
		// TODO: Implement update() method.
		$question = wp_parse_args( $args, array(
			'action'  => '',
			'id'      => '',
			'title'   => '',
			'content' => '',
			'key'     => '',
			'meta'    => ''
		) );


		switch ( $question['action'] ) {
			case 'update-title':
				wp_update_post( array( 'ID' => $question['id'], 'post_title' => $question['title'] ) );
				break;
			case 'update-content':
				wp_update_post( array( 'ID' => $question['id'], 'post_content' => $question['content'] ) );
				break;
			case 'update-meta':
				if ( ! $question['key'] ) {
					break;
				}
				update_post_meta( $question['id'], '_lp_' . $question['key'], $question['meta'] );
				break;
			default:
				break;
		}

		return $question;
	}


	/**
	 * Convert answers to true or false question.
	 *
	 * @since 3.0.0
	 *
	 * @param $answers
	 *
	 * @return mixed
	 */
	protected function _convert_answers_to_true_or_false( $answers ) {

		if ( is_array( $answers ) ) {
			if ( sizeof( $answers ) > 2 ) {
				global $wpdb;
				foreach ( $answers as $key => $answer ) {
					if ( $key > 1 ) {
						$wpdb->delete(
							$wpdb->learnpress_question_answers,
							array( 'question_answer_id' => $answer['question_answer_id'] )
						);
					}
				}
				$answers = array_slice( $answers, 0, 2 );
			}

			$correct = 0;
			foreach ( $answers as $key => $answer ) {
				if ( $answer['is_true'] == 'yes' ) {
					$correct += 1;
				}
			}

			if ( ! $correct ) {
				// for single choice deletes all correct, set first option is correct
				$answers[0]['is_true'] = 'yes';
			} else if ( $correct == 2 ) {
				// for multiple choice keeps all correct, remove all correct and keep first option
				$answers[1]['is_true'] = '';
			}
		}

		return $answers;
	}

	/**
	 *
	 * Convert answers for multi choice to single choice question.
	 *
	 * @since 3.0.0
	 *
	 * @param $answers
	 *
	 * @return array
	 */
	protected function _convert_answers_multi_choice_to_single_choice( $answers ) {
		if ( is_array( $answers ) ) {

			$correct = 0;
			foreach ( $answers as $key => $answer ) {
				if ( $answer['is_true'] == 'yes' ) {
					$correct += 1;
				}
			}

			if ( $correct > 1 ) {
				// remove all correct and keep first option
				$answers[0]['is_true'] = '';
			}
		}

		return $answers;
	}

	/**
	 * Change question type.
	 *
	 * @since 3.0.0
	 *
	 * @param $question_id
	 * @param $old_type
	 * @param $new_type
	 *
	 * @return bool|LP_Question|mixed
	 */
	public function change_question_type( $question_id, $old_type, $new_type ) {

		$old_question   = LP_Question::get_question( $question_id );
		$answer_options = $old_question->get_data( 'answer_options' );

		update_post_meta( $question_id, '_lp_type', $new_type );

		if ( $new_question = LP_Question::get_question( $question_id, array( 'force' => true ) ) ) {

			// except convert from true or false
			if ( ! ( ( $old_type == 'true_or_false' ) && ( $old_type == 'single_choice' && $new_type == 'multi_choice' ) ) ) {
				if ( $new_type == 'true_or_false' ) {
					$func = "_convert_answers_to_true_or_false";
				} else {
					$func = "_convert_answers_{$old_type}_to_{$new_type}";
				}
				if ( is_callable( array( $this, $func ) ) ) {
					$answer_options = call_user_func_array( array( $this, $func ), array( $answer_options ) );
				}
			}

			wp_cache_delete( 'answer-options-' . $question_id, 'lp-questions' );
			wp_cache_set( 'answer-options-' . $question_id, $answer_options, 'lp-questions' );
			$new_question->set_data( 'answer_options', $answer_options );

			return $new_question;
		}

		return false;
	}

	/**
	 * Update question answer.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args
	 * @param string $case
	 *
	 * @return false|int
	 */
	public function update_answer( $args = array(), $case = '' ) {

		if ( ! $case ) {
			return false;
		}

		global $wpdb;

		$answer = false;

		switch ( $case ) {
			case 'update-title':
				$answer = $wpdb->update( $wpdb->learnpress_question_answers,
					$args['data'],
					$args['where'],
					array( '%s', '%s', '%s' ),
					array( '%d', '%d', '%d' )
				);
				break;
			case 'update-correct':
				foreach ( $args as $arg ) {
					$answer = $wpdb->update( $wpdb->learnpress_question_answers,
						$arg['data'],
						$arg['where'],
						array( '%s', '%s', '%s' ),
						array( '%d', '%d', '%d' )
					);
				}
				break;
			default:
				break;
		}

		return $answer;
	}

	/**
	 * Add question answer.
	 *
	 * @since 3.0.0
	 *
	 * @param array $args
	 *
	 * @return array|bool
	 */
	public function add_answer( $args = array() ) {

		global $wpdb;

		$wpdb->insert(
			$wpdb->learnpress_question_answers,
			array(
				'question_id'  => $args['question_id'],
				'answer_data'  => $args['answer_data'],
				'answer_order' => $args['answer_order']
			),
			array( '%d', '%s', '%d' ) );

		$question_answer_id = $wpdb->insert_id;
		if ( $question_answer_id ) {
			return array(
				'question_answer_id' => $question_answer_id,
				'question_id'        => $args['question_id'],
				'answer_order'       => $args['answer_order'],
				'text'               => unserialize( $args['answer_data'] )['text'],
				'value'              => unserialize( $args['answer_data'] )['value'],
				'is_true'            => unserialize( $args['answer_data'] )['is_true']
			);
		} else {
			return false;
		}
	}

	/**
	 * Delete question answer.
	 *
	 * @since 3.0.0
	 *
	 * @param $question_id
	 * @param $answer_id
	 *
	 * @return bool|false|int
	 */
	public function delete_question_answer( $question_id, $answer_id ) {
		if ( get_post_type( $question_id ) !== LP_QUESTION_CPT || ! $answer_id ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete(
			$wpdb->learnpress_question_answers,
			array( 'question_answer_id' => $answer_id )
		);

		return $result;
	}

	/**
	 * Delete all question answers.
	 *
	 * @since 3.0.0
	 *
	 * @param $question_id
	 *
	 * @return bool|false|int
	 */
	public function delete_question_answers( $question_id ) {
		if ( get_post_type( $question_id ) !== LP_QUESTION_CPT ) {
			return false;
		}

		global $wpdb;

		$result = $wpdb->delete( $wpdb->learnpress_question_answers, array( 'question_id' => $question_id ) );

		learn_press_reset_auto_increment( 'learnpress_quiz_questions' );

		return $result;
	}

	/**
	 * Load answer options for the question from database.
	 * Load from cache if data is already loaded into cache.
	 * Otherwise, load from database and put to cache.
	 *
	 * @param $question LP_Question
	 */
	protected function _load_answer_options( &$question ) {
		$id             = $question->get_id();
		$answer_options = wp_cache_get( 'answer-options-' . $id, 'lp-questions' );

		if ( false === $answer_options ) {
			global $wpdb;
			$query = $wpdb->prepare( "
				SELECT *
				FROM {$wpdb->prefix}learnpress_question_answers
				WHERE question_id = %d
				ORDER BY answer_order ASC
			", $id );
			if ( $answer_options = $wpdb->get_results( $query, OBJECT_K ) ) {
				foreach ( $answer_options as $k => $v ) {
					$answer_options[ $k ] = (array) $answer_options[ $k ];
					if ( $answer_data = maybe_unserialize( $v->answer_data ) ) {
						foreach ( $answer_data as $data_key => $data_value ) {
							$answer_options[ $k ][ $data_key ] = $data_value;
						}
					}
					unset( $answer_options[ $k ]['answer_data'] );
				}
			}
		}
		$answer_options = apply_filters( 'learn-press/question/load-answer-options', $answer_options, $id );

		if ( ! empty( $answer_options['question_answer_id'] ) && $answer_options['question_answer_id'] > 0 ) {
			$this->_load_answer_option_meta( $answer_options );
		}
		wp_cache_set( 'answer-options-' . $id, $answer_options, 'lp-questions' );

		$question->set_data( 'answer_options', $answer_options );
	}

	/**
	 * Load meta data for answer options.
	 *
	 * @param array $answer_options
	 *
	 * @return mixed;
	 */
	protected function _load_answer_option_meta( &$answer_options ) {
		global $wpdb;
		if ( ! $answer_options ) {
			return false;
		}
		$answer_option_ids = wp_list_pluck( $answer_options, 'question_answer_id' );
		$format            = array_fill( 0, sizeof( $answer_option_ids ), '%d' );
		$query             = $wpdb->prepare( "
			SELECT *
			FROM {$wpdb->prefix}learnpress_question_answermeta
			WHERE learnpress_question_answer_id IN(" . join( ', ', $format ) . ")
		", $answer_option_ids );
		if ( $metas = $wpdb->get_results( $query ) ) {
			foreach ( $metas as $meta ) {
				$key        = $meta->meta_key;
				$option_key = $meta->learnpress_question_answer_id;
				if ( ! empty( $answer_options[ $option_key ] ) ) {
					if ( $key == 'checked' ) {
						$key = 'is_true';
					}
					$answer_options[ $option_key ][ $key ] = $meta->meta_value;
				}
			}
		}

		return true;
	}

	public function add_meta( &$object, $meta ) {
		// TODO: Implement add_meta() method.
	}

	public function delete_meta( &$object, $meta ) {
		// TODO: Implement delete_meta() method.
	}

	public function read_meta( &$object ) {
		// TODO: Implement read_meta() method.
	}

	public function update_meta( &$object, $meta ) {
		// TODO: Implement update_meta() method.
	}
}