<?php

/**
 * Class LP_Course_CURD
 *
 * @author  ThimPress
 * @package LearnPress/Classes/CURD
 * @since   3.0.0
 */

/**
 * Prevent loading this file directly
 */
defined( 'ABSPATH' ) || exit();

if ( ! class_exists( 'LP_Course_CURD' ) ) {
	/**
	 * Class LP_Course_CURD
	 */
	class LP_Course_CURD extends LP_Object_Data_CURD implements LP_Interface_CURD {

		public function create( &$course ) {
			// TODO: Implement create() method.
		}

		public function update( &$course ) {
			// TODO: Implement update() method.
		}

		public function delete( &$course ) {
			// TODO: Implement delete() method.
		}

		/**
		 * Load course data
		 *
		 * @param LP_Course $course
		 *
		 * @return mixed
		 */
		public function load( &$course ) {
			$this->load_curriculum( $course );
			$this->load_data( $course );

			return $course;
		}

		/**
		 * Load course curriculum.
		 *
		 * @param LP_Course $course
		 */
		protected function load_curriculum( &$course ) {
			$course_id = $course->get_id();
			$this->read_course_curriculum( $course_id );
		}

		/**
		 * @param LP_Course $course
		 */
		public function load_data( &$course ) {
			$id          = $course->get_id();
			$post_object = get_post( $id );
			$course->set_data(
				array(
					'status'               => $post_object->post_status,
					'require_enrollment'   => get_post_meta( $id, '_lp_required_enroll', true ),
					'price'                => get_post_meta( $id, '_lp_price', true ),
					'sale_price'           => get_post_meta( $id, '_lp_sale_price', true ),
					'sale_start'           => get_post_meta( $id, '_lp_sale_start', true ),
					'sale_end'             => get_post_meta( $id, '_lp_sale_end', true ),
					'duration'             => get_post_meta( $id, '_lp_duration', true ),
					'max_students'         => get_post_meta( $id, '_lp_max_students', true ),
					'students'             => get_post_meta( $id, '_lp_students', true ),
					'retake_count'         => get_post_meta( $id, '_lp_retake_count', true ),
					'featured'             => get_post_meta( $id, '_lp_featured', true ),
					'block_lesson_content' => get_post_meta( $id, '_lp_block_lesson_content', true ),
					'course_result'        => get_post_meta( $id, '_lp_course_result', true ),
					'passing_condition'    => get_post_meta( $id, '_lp_passing_condition', true ),
					'payment'              => get_post_meta( $id, '_lp_payment', true ),
					'final_quiz'           => get_post_meta( $id, '_lp_final_quiz', true ),
					'external_link'        => get_post_meta( $id, '_lp_external_link_buy_course', true )
				)
			);
		}

		/**
		 * Read curriculum of a course from db into cache.
		 *
		 * @param $course_id
		 *
		 * @return bool
		 */
		public function read_course_curriculum( $course_id ) {

			global $wpdb;

			if ( is_numeric( $course_id ) ) {
				settype( $course_id, 'array' );
			}

			$fetch_ids = array();

			/**
			 * Get course's data from cache and if it is already existed
			 * then ignore that course.
			 */
			foreach ( $course_id as $id ) {
				if ( false === ( $data = wp_cache_get( 'course-' . $id, 'lp-course-curriculum' ) ) ) {
					$fetch_ids[] = $id;
				}
			}

			// There is no course ids to read
			if ( ! $fetch_ids ) {
				return false;
			}

			// Read course sections
			$this->read_course_sections( $fetch_ids );

			$all_section_ids = array();
			foreach ( $fetch_ids as $id ) {
				if ( $sections = $this->get_course_sections( $id ) ) {
					$section_ids     = wp_list_pluck( $sections, 'section_id' );
					$all_section_ids = array_merge( $all_section_ids, $section_ids );
				}

				// Set cache
				wp_cache_set( 'course-' . $id, array(), 'lp-course-curriculum' );
			}

			if ( $all_section_ids ) {
				$format        = array_fill( 0, sizeof( $all_section_ids ), '%d' );
				$post_statuses = array( 'publish' );
				$query_args    = array_merge( $all_section_ids, $post_statuses );

				$query = $wpdb->prepare( "
				SELECT s.*, si.*, p.*
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->learnpress_section_items} si ON si.item_id = p.ID
				INNER JOIN {$wpdb->learnpress_sections} s ON s.section_id = si.section_id
				WHERE s.section_id IN(" . join( ',', $format ) . ")
				AND p.post_status IN(%s)
				ORDER BY s.section_course_id, s.section_order, si.item_order ASC
			", $query_args );

				$item_ids = array();

				if ( $results = $wpdb->get_results( $query ) ) {
					$curriculum = array();
					$cur_id     = 0;
					$post_cache = (array) get_post( 1 );
					foreach ( $results as $row ) {
						// Switch to other course
						if ( $row->section_course_id !== $cur_id ) {

							// If $cur_id is already set to a course
							if ( $cur_id ) {
								//wp_cache_set( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );
								wp_cache_set( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );
							}

							// Set $cur_id to new course and reset $curriculum
							$cur_id     = $row->section_course_id;
							$curriculum = array();
						}

						$curriculum[] = $row;
						if ( empty( $item_ids[ $row->section_course_id ] ) ) {
							$item_ids[ $row->section_course_id ] = array( $row->ID );
						} else {
							$item_ids[ $row->section_course_id ][] = $row->ID;
						}
						// Cache post for using get_post() later.
						$_post = array_intersect_key( (array) $row, $post_cache );
						$_post = sanitize_post( $_post, 'raw' );
						$_post = (object) $_post;
						//wp_cache_add( $_post->ID, $_post, 'posts' );
						wp_cache_set( $_post->ID, $_post, 'posts' );
					}
					//wp_cache_set( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );
					wp_cache_set( 'course-' . $cur_id, $curriculum, 'lp-course-curriculum' );

					// Cache items ids for using in some cases
					foreach ( $item_ids as $cid => $ids ) {
						wp_cache_set( 'course-' . $cid, $ids, 'lp-course-items' );
					}
					unset( $curriculum );
				}
			}

			return true;
		}

		/**
		 * Get sections of course
		 *
		 * @param $course_id
		 *
		 * @return array
		 */
		public function get_course_sections( $course_id ) {
			$this->read_course_sections( $course_id );

			return wp_cache_get( 'course-' . $course_id, 'lp-course-sections' );
		}

		/**
		 * Read sections of a bundle of courses by ids
		 *
		 * @param int|array $course_id
		 *
		 * @return mixed|array
		 */
		public function read_course_sections( $course_id ) {
			global $wpdb;

			if ( is_numeric( $course_id ) ) {
				settype( $course_id, 'array' );
			}

			$fetch_ids = array();

			/**
			 * Get course's data from cache and if it is already existed
			 * then ignore that course.
			 */
			foreach ( $course_id as $id ) {
				if ( false === wp_cache_get( 'course-' . $id, 'lp-course-sections' ) ) {
					$fetch_ids[] = $id;
				}
			}

			// There is no course ids to read
			if ( ! $fetch_ids ) {
				return false;
			}

			$format = array_fill( 0, sizeof( $fetch_ids ), '%d' );
			$query  = $wpdb->prepare( "
			SELECT s.*
			FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->learnpress_sections} s ON p.ID = s.section_course_id
			WHERE p.ID IN(" . join( ',', $format ) . ")
			ORDER BY p.ID, `section_order` ASC
		", $fetch_ids );

			if ( $results = $wpdb->get_results( $query ) ) {
				$course_sections = array();
				$cur_id          = 0;
				foreach ( $results as $row ) {
					if ( $row->section_course_id !== $cur_id ) {
						if ( $cur_id ) {
							wp_cache_set( 'course-' . $cur_id, $course_sections, 'lp-course-sections' );
						}
						$cur_id          = $row->section_course_id;
						$course_sections = array();
					}
					$course_sections[] = $row;
				}
				wp_cache_set( 'course-' . $cur_id, $course_sections, 'lp-course-sections' );
				unset( $course_sections );
			}

			return true;
		}

		/**
		 * Remove lesson, quiz from course.
		 *
		 * @since 3.0.0
		 *
		 * @param $item_id
		 */
		public function remove_item_from_course( $item_id ) {

			global $wpdb;

			// delete item from course's section
			$query = $wpdb->prepare( "
				DELETE FROM {$wpdb->prefix}learnpress_section_items
				WHERE item_id = %d
			", $item_id );

			$wpdb->query( $query );

			learn_press_reset_auto_increment( 'learnpress_section_items' );
		}

		/**
		 * Remove all course data from database, includes section and course's items
		 *
		 * @param $course_id
		 */
		public function remove_course( $course_id ) {
			global $wpdb;

			$section_ids = $wpdb->get_col( $wpdb->prepare( "SELECT section_id FROM {$wpdb->prefix}learnpress_sections WHERE section_course_id = %d", $course_id ) );
			if ( $section_ids ) {
				$wpdb->query(
					$wpdb->prepare( "DELETE FROM {$wpdb->prefix}learnpress_section_items WHERE %d AND section_id IN(" . join( ',', $section_ids ) . ")", 1 )
				);
				learn_press_reset_auto_increment( 'learnpress_section_items' );
			}

			// delete all sections
			$query = $wpdb->prepare( "
					DELETE FROM {$wpdb->prefix}learnpress_sections
					WHERE section_course_id = %d
					", $course_id );
			$wpdb->query( $query );
			learn_press_reset_auto_increment( 'learnpress_sections' );
		}

		/**
		 * Get recent courses.
		 *
		 * @param array $args
		 *
		 * @return array
		 */
		public function get_recent_courses( $args = array() ) {
			global $wpdb;

			$limit = ! empty( $args['limit'] ) ? $args['limit'] : - 1;
			$order = ! empty( $args['order'] ) ? $args['order'] : 'DESC';

			$query = apply_filters( 'learn-press/course-curd/query-recent-courses',
				$wpdb->prepare(
					"SELECT DISTINCT p.ID FROM $wpdb->posts AS p
						WHERE p.post_type = %s
						AND p.post_status = %s
						ORDER BY p.post_date {$order}
						LIMIT %d
					",
					LP_COURSE_CPT, 'publish', $limit
				) );

			return $query;
		}

		/**
		 * Get feature courses.
		 *
		 * @param array $args
		 *
		 * @return array
		 */
		public function get_featured_courses( $args = array() ) {
			global $wpdb;

			$limit    = ! empty( $args['limit'] ) ? $args['limit'] : - 1;
			$order_by = ! empty( $args['order_by'] ) ? $args['order_by'] : 'post_date';
			$order    = ! empty( $args['order'] ) ? $args['order'] : 'DESC';

			$query = apply_filters( 'learn-press/course-curd/query-feature-courses',
				$wpdb->prepare( "
				SELECT DISTINCT p.ID FROM {$wpdb->posts} p
                    LEFT JOIN {$wpdb->postmeta} as pmeta ON p.ID=pmeta.post_id AND pmeta.meta_key = %s
                    WHERE p.post_type = %s
						AND p.post_status = %s
						AND meta_value = %s
                    ORDER BY p.{$order_by} {$order}
                    LIMIT %d
                ", '_lp_featured', LP_COURSE_CPT, 'publish', 'yes', $limit )
			);

			return $query;
		}

		/**
		 * Get popular courses.
		 *
		 * @param array $args
		 *
		 * @return array
		 */
		public function get_popular_courses( $args = array() ) {
			global $wpdb;

			$limit = ! empty( $args['limit'] ) ? $args['limit'] : - 1;
			$order = ! empty( $args['order'] ) ? $args['order'] : 'DESC';

			$query = apply_filters( 'learn-press/course-curd/query-popular-courses',
				$wpdb->prepare(
					"SELECT DISTINCT p.*, count(*) as number_enrolled FROM {$wpdb->prefix}learnpress_user_items ui
					INNER JOIN {$wpdb->posts} p ON p.ID = ui.item_id
					WHERE ui.item_type = %s
						AND ( ui.status = %s OR ui.status = %s )
						AND p.post_status = %s
					ORDER BY ui.item_id {$order}
					LIMIT %d
				", LP_COURSE_CPT, 'enrolled', 'finished', 'publish', $limit
				) );

			return $query;
		}
	}
}