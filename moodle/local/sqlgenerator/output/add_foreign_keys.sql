/* Moodle version 2019111802 Release 3.8.2 (Build: 20200309) Add Foreign Keys code */
ALTER TABLE mdl_block_rss_client ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_competency_userevidencecomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_plancomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_usercompplan ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_usercompcourse ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_usercomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_relatedcomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_templatecomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_modulecomp ADD FOREIGN KEY (competencyid) REFERENCES mdl_competency (id);
ALTER TABLE mdl_competency_plan ADD FOREIGN KEY (templateid) REFERENCES mdl_competency_template (id);
ALTER TABLE mdl_competency_templatecohort ADD FOREIGN KEY (templateid) REFERENCES mdl_competency_template (id);
ALTER TABLE mdl_competency ADD FOREIGN KEY (competencyframeworkid) REFERENCES mdl_competency_framework (id);
ALTER TABLE mdl_competency_evidence ADD FOREIGN KEY (usercompetencyid) REFERENCES mdl_competency_usercomp (id);
ALTER TABLE mdl_competency_userevidence ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_course_modules ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_published ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_sections ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_completions ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_completion_aggr_methd ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_completion_crit_compl ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_course_categories ADD FOREIGN KEY (parent) REFERENCES mdl_course_categories (id);
ALTER TABLE mdl_course ADD FOREIGN KEY (category) REFERENCES mdl_course_categories (id);
ALTER TABLE mdl_course_modules_completion ADD FOREIGN KEY (coursemoduleid) REFERENCES mdl_course_modules (id);
ALTER TABLE mdl_user_preferences ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_book_chapters ADD FOREIGN KEY (bookid) REFERENCES mdl_book (id);
ALTER TABLE mdl_enrol_paypal ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_forum_read ADD FOREIGN KEY (forumid) REFERENCES mdl_forum (id);
ALTER TABLE mdl_grade_letters ADD FOREIGN KEY (contextid) REFERENCES mdl_context (id);
ALTER TABLE mdl_folder ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_imscp ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_label ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_lti_types_config ADD FOREIGN KEY (typeid) REFERENCES mdl_lti (id);
ALTER TABLE mdl_lti ADD FOREIGN KEY (typeid) REFERENCES mdl_lti_types (id);
ALTER TABLE mdl_lti_submission ADD FOREIGN KEY (ltiid) REFERENCES mdl_lti (id);
ALTER TABLE mdl_log ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_question_statistics ADD FOREIGN KEY (questionid) REFERENCES mdl_question (id);
ALTER TABLE mdl_user_lastaccess ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_user_info_data ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_mnet_service2rpc ADD FOREIGN KEY (serviceid) REFERENCES mdl_mnet_service (id);
ALTER TABLE mdl_message_contacts ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_message_read ADD FOREIGN KEY (useridfrom) REFERENCES mdl_user (id);
ALTER TABLE mdl_message_popup ADD FOREIGN KEY (messageid) REFERENCES mdl_message (id);
ALTER TABLE mdl_my_pages ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_mnet_session ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_mnet_log ADD FOREIGN KEY (hostid) REFERENCES mdl_mnet_host (id);
ALTER TABLE mdl_page ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_resource ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_block_instances ADD FOREIGN KEY (blockname) REFERENCES mdl_block (name);
ALTER TABLE mdl_block_recent_activity ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_forum_track_prefs ADD FOREIGN KEY (userid) REFERENCES mdl_forum (id);
ALTER TABLE mdl_forum_read ADD FOREIGN KEY (discussionid) REFERENCES mdl_forum_discussions (id);
ALTER TABLE mdl_forum_read ADD FOREIGN KEY (postid) REFERENCES mdl_forum_posts (id);
ALTER TABLE mdl_course_completion_criteria ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_tool_cohortroles ADD FOREIGN KEY (cohortid) REFERENCES mdl_cohort (id);
ALTER TABLE mdl_user_info_field ADD FOREIGN KEY (categoryid) REFERENCES mdl_user_info_category (id);
ALTER TABLE mdl_user_info_data ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_user_preferences ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_user_lastaccess ADD FOREIGN KEY (userid) REFERENCES mdl_user (id);
ALTER TABLE mdl_repository_instance_config ADD FOREIGN KEY (instanceid) REFERENCES mdl_repository_instances (id);
ALTER TABLE mdl_url ADD FOREIGN KEY (course) REFERENCES mdl_course (id);
ALTER TABLE mdl_wiki_synonyms ADD FOREIGN KEY (subwikiid) REFERENCES mdl_wiki_subwikis (id);
ALTER TABLE mdl_wiki_synonyms ADD FOREIGN KEY (pageid) REFERENCES mdl_wiki_pages (id);
ALTER TABLE mdl_wiki_locks ADD FOREIGN KEY (pageid) REFERENCES mdl_wiki_pages (id);
ALTER TABLE mdl_backup_courses ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_daily ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_monthly ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_weekly ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_user_daily ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_user_monthly ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_stats_user_weekly ADD FOREIGN KEY (courseid) REFERENCES mdl_course (id);
ALTER TABLE mdl_workshopform_rubric ADD FOREIGN KEY (workshopid) REFERENCES mdl_workshop (id);
ALTER TABLE mdl_workshopform_rubric_config ADD FOREIGN KEY (workshopid) REFERENCES mdl_workshop (id);
/* End of Extra Foreign Keys */ 
