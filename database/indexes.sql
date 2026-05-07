-- KIBARE-JOB recommended MySQL 8 indexes.

CREATE INDEX idx_users_active_deleted ON users (is_active, is_deleted);
CREATE INDEX idx_users_subscription ON users (subscription_tier, subscription_expiry);
CREATE INDEX idx_users_last_swipe_reset ON users (last_swipe_reset);

CREATE INDEX idx_candidate_profiles_city ON candidate_profiles (city);
CREATE INDEX idx_candidate_profiles_education ON candidate_profiles (education_level);
CREATE INDEX idx_candidate_profiles_availability ON candidate_profiles (availability);
CREATE INDEX idx_candidate_profiles_deleted ON candidate_profiles (is_deleted);

CREATE INDEX idx_employeurs_sector ON employeurs (sector);
CREATE INDEX idx_employeurs_validated_deleted ON employeurs (is_validated, is_deleted);
CREATE INDEX idx_employeurs_subscription ON employeurs (subscription_tier, subscription_expiry);

CREATE INDEX idx_job_offers_employer ON job_offers (employer_id);
CREATE INDEX idx_job_offers_feed ON job_offers (status, is_deleted, deadline, created_at);
CREATE INDEX idx_job_offers_location ON job_offers (location);
CREATE INDEX idx_job_offers_contract ON job_offers (contract_type);
CREATE INDEX idx_job_offers_boosted ON job_offers (is_boosted, created_at);

CREATE INDEX idx_candidate_documents_candidate ON candidate_documents (candidate_id);
CREATE INDEX idx_candidate_documents_type ON candidate_documents (type);
CREATE INDEX idx_candidate_documents_hash ON candidate_documents (file_hash);
CREATE INDEX idx_candidate_documents_public_verified ON candidate_documents (is_public, is_verified, is_deleted);

CREATE INDEX idx_swipes_candidate_sent ON swipes (candidate_id, sent_at);
CREATE INDEX idx_swipes_offer_status ON swipes (offer_id, status);
CREATE INDEX idx_swipes_direction ON swipes (direction);

CREATE INDEX idx_document_requests_employer_status ON document_requests (employer_id, status);
CREATE INDEX idx_document_requests_candidate_status ON document_requests (candidate_id, status);
CREATE INDEX idx_document_requests_offer ON document_requests (offer_id);

CREATE INDEX idx_notifications_user_read_created ON notifications (user_id, is_read, created_at);
CREATE INDEX idx_notifications_type ON notifications (type);

CREATE INDEX idx_payments_user_created ON payments (user_id, created_at);
CREATE INDEX idx_payments_status ON payments (status);
CREATE INDEX idx_payments_transaction ON payments (transaction_id);

CREATE INDEX idx_document_verification_logs_document ON document_verification_logs (document_id, verification_date);
CREATE INDEX idx_document_verification_logs_admin ON document_verification_logs (admin_id);
CREATE INDEX idx_document_verification_logs_action ON document_verification_logs (action);

CREATE INDEX idx_subscription_plans_target ON subscription_plans (target, is_deleted);
CREATE INDEX idx_stats_cache_calculated_at ON stats_cache (calculated_at);
