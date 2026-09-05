-- HAIP 1.0 / direct_post.jwt additions.
-- TYPO3 normally applies ext_tables.sql changes through Analyze Database Structure.

ALTER TABLE tx_eudiwalletintegrationintegration_session
    ADD response_encryption_kid varchar(128) NOT NULL DEFAULT '';

CREATE INDEX response_encryption_kid
    ON tx_eudiwalletintegrationintegration_session (response_encryption_kid);

ALTER TABLE tx_eudiwalletintegrationintegration_trust_anchor
    ADD authority_key_identifier varchar(255) NOT NULL DEFAULT '';

ALTER TABLE tx_eudiwalletintegrationintegration_session
    ADD same_device smallint unsigned NOT NULL DEFAULT 0,
    ADD same_device_confirmed smallint unsigned NOT NULL DEFAULT 0,
    ADD response_code_hash char(64) NOT NULL DEFAULT '',
    ADD response_code_consumed smallint unsigned NOT NULL DEFAULT 0;
