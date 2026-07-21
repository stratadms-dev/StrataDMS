-- public."groups" definition

-- Drop table

-- DROP TABLE public."groups";

CREATE TABLE public."groups" (
	id serial4 NOT NULL,
	"name" varchar(255) NOT NULL,
	CONSTRAINT groups_name_key UNIQUE (name),
	CONSTRAINT groups_pkey PRIMARY KEY (id)
);


-- public.settings definition

-- Drop table

-- DROP TABLE public.settings;

CREATE TABLE public.settings (
	setting_key varchar(255) NOT NULL,
	setting_value text NULL,
	CONSTRAINT settings_pkey PRIMARY KEY (setting_key)
);


-- public.users definition

-- Drop table

-- DROP TABLE public.users;

CREATE TABLE public.users (
	id serial4 NOT NULL,
	username varchar(50) NOT NULL,
	password_hash varchar(255) NOT NULL,
	"role" varchar(20) DEFAULT 'user'::character varying NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT users_pkey PRIMARY KEY (id),
	CONSTRAINT users_username_key UNIQUE (username)
);


-- public.activity_log definition

-- Drop table

-- DROP TABLE public.activity_log;

CREATE TABLE public.activity_log (
	id serial4 NOT NULL,
	user_id int4 NOT NULL,
	action_type varchar(50) NOT NULL,
	item_name text NOT NULL,
	target_name text NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT activity_log_pkey PRIMARY KEY (id),
	CONSTRAINT activity_log_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id)
);


-- public.document_templates definition

-- Drop table

-- DROP TABLE public.document_templates;

CREATE TABLE public.document_templates (
	id serial4 NOT NULL,
	"name" varchar(255) NOT NULL,
	description text NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	created_by int4 NULL,
	CONSTRAINT document_templates_pkey PRIMARY KEY (id),
	CONSTRAINT document_templates_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id)
);


-- public.folders definition

-- Drop table

-- DROP TABLE public.folders;

CREATE TABLE public.folders (
	id serial4 NOT NULL,
	"name" varchar(100) NOT NULL,
	parent_id int4 NULL,
	created_by int4 NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	is_deleted bool DEFAULT false NULL,
	deleted_at timestamp NULL,
	deleted_by int4 NULL,
	inherit_permissions bool DEFAULT true NULL,
	updated_by int4 NULL,
	updated_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT folders_pkey PRIMARY KEY (id),
	CONSTRAINT folders_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id),
	CONSTRAINT folders_parent_id_fkey FOREIGN KEY (parent_id) REFERENCES public.folders(id) ON DELETE CASCADE,
	CONSTRAINT folders_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES public.users(id)
);


-- public.stamps definition

-- Drop table

-- DROP TABLE public.stamps;

CREATE TABLE public.stamps (
	id serial4 NOT NULL,
	"name" varchar(255) NOT NULL,
	stamp_text varchar(255) NOT NULL,
	font varchar(50) DEFAULT 'Helvetica-Bold'::character varying NULL,
	font_size int4 DEFAULT 36 NULL,
	color varchar(20) DEFAULT '#FF0000'::character varying NULL,
	created_by int4 NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT stamps_pkey PRIMARY KEY (id),
	CONSTRAINT stamps_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id)
);


-- public.template_fields definition

-- Drop table

-- DROP TABLE public.template_fields;

CREATE TABLE public.template_fields (
	id serial4 NOT NULL,
	template_id int4 NULL,
	"name" varchar(255) NOT NULL,
	"type" varchar(50) NOT NULL,
	"options" jsonb NULL,
	is_required bool DEFAULT false NULL,
	order_index int4 DEFAULT 0 NULL,
	CONSTRAINT template_fields_pkey PRIMARY KEY (id),
	CONSTRAINT template_fields_template_id_fkey FOREIGN KEY (template_id) REFERENCES public.document_templates(id) ON DELETE CASCADE
);


-- public.user_groups definition

-- Drop table

-- DROP TABLE public.user_groups;

CREATE TABLE public.user_groups (
	user_id int4 NOT NULL,
	group_id int4 NOT NULL,
	CONSTRAINT user_groups_pkey PRIMARY KEY (user_id, group_id),
	CONSTRAINT user_groups_group_id_fkey FOREIGN KEY (group_id) REFERENCES public."groups"(id) ON DELETE CASCADE,
	CONSTRAINT user_groups_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);


-- public.documents definition

-- Drop table

-- DROP TABLE public.documents;

CREATE TABLE public.documents (
	id serial4 NOT NULL,
	title varchar(255) NOT NULL,
	filename varchar(255) NOT NULL,
	folder_id int4 NULL,
	uploaded_by int4 NULL,
	file_size int4 NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	thumbnail_filename varchar(255) NULL,
	is_deleted bool DEFAULT false NULL,
	deleted_at timestamp NULL,
	deleted_by int4 NULL,
	template_id int4 NULL,
	checked_out_at timestamp NULL,
	checked_out_by int4 NULL,
	updated_by int4 NULL,
	updated_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT documents_filename_key UNIQUE (filename),
	CONSTRAINT documents_pkey PRIMARY KEY (id),
	CONSTRAINT documents_checked_out_by_fkey FOREIGN KEY (checked_out_by) REFERENCES public.users(id) ON DELETE SET NULL,
	CONSTRAINT documents_folder_id_fkey FOREIGN KEY (folder_id) REFERENCES public.folders(id) ON DELETE CASCADE,
	CONSTRAINT documents_template_id_fkey FOREIGN KEY (template_id) REFERENCES public.document_templates(id),
	CONSTRAINT documents_updated_by_fkey FOREIGN KEY (updated_by) REFERENCES public.users(id),
	CONSTRAINT documents_uploaded_by_fkey FOREIGN KEY (uploaded_by) REFERENCES public.users(id)
);


-- public.folder_group_permissions definition

-- Drop table

-- DROP TABLE public.folder_group_permissions;

CREATE TABLE public.folder_group_permissions (
	id serial4 NOT NULL,
	group_id int4 NOT NULL,
	folder_id int4 NOT NULL,
	"scope" varchar(255) DEFAULT 'this_folder_subfolders_documents'::character varying NULL,
	right_view bool NULL,
	right_add bool NULL,
	right_modify bool NULL,
	right_delete bool NULL,
	right_see_through_redactions bool NULL,
	right_manage_security bool NULL,
	CONSTRAINT folder_group_permissions_group_id_folder_id_key UNIQUE (group_id, folder_id),
	CONSTRAINT folder_group_permissions_pkey PRIMARY KEY (id),
	CONSTRAINT folder_group_permissions_folder_id_fkey FOREIGN KEY (folder_id) REFERENCES public.folders(id) ON DELETE CASCADE,
	CONSTRAINT folder_group_permissions_group_id_fkey FOREIGN KEY (group_id) REFERENCES public."groups"(id) ON DELETE CASCADE
);


-- public.folder_permissions definition

-- Drop table

-- DROP TABLE public.folder_permissions;

CREATE TABLE public.folder_permissions (
	id serial4 NOT NULL,
	user_id int4 NOT NULL,
	folder_id int4 NULL,
	"scope" varchar(50) DEFAULT 'this_folder_subfolders_documents'::character varying NOT NULL,
	right_view bool NULL,
	right_add bool NULL,
	right_modify bool NULL,
	right_delete bool NULL,
	right_see_through_redactions bool NULL,
	right_manage_security bool NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	updated_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT folder_permissions_pkey PRIMARY KEY (id),
	CONSTRAINT folder_permissions_user_id_folder_id_key UNIQUE (user_id, folder_id),
	CONSTRAINT folder_permissions_folder_id_fkey FOREIGN KEY (folder_id) REFERENCES public.folders(id) ON DELETE CASCADE,
	CONSTRAINT folder_permissions_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);


-- public.user_recent_documents definition

-- Drop table

-- DROP TABLE public.user_recent_documents;

CREATE TABLE public.user_recent_documents (
	id serial4 NOT NULL,
	user_id int4 NULL,
	document_id int4 NULL,
	viewed_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT user_recent_documents_pkey PRIMARY KEY (id),
	CONSTRAINT user_recent_documents_user_id_document_id_key UNIQUE (user_id, document_id),
	CONSTRAINT user_recent_documents_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE,
	CONSTRAINT user_recent_documents_user_id_fkey FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE
);


-- public.watermarks definition

-- Drop table

-- DROP TABLE public.watermarks;

CREATE TABLE public.watermarks (
	id serial4 NOT NULL,
	document_id int4 NULL,
	"text" text NOT NULL,
	h_pos varchar(20) DEFAULT 'center'::character varying NULL,
	v_pos varchar(20) DEFAULT 'center'::character varying NULL,
	rotation int4 DEFAULT 0 NULL,
	size_pct int4 DEFAULT 50 NULL,
	opacity int4 DEFAULT 50 NULL,
	is_active bool DEFAULT true NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	image_filename text NULL,
	offset_x int4 DEFAULT 0 NULL,
	offset_y int4 DEFAULT 0 NULL,
	CONSTRAINT watermarks_pkey PRIMARY KEY (id),
	CONSTRAINT watermarks_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE
);


-- public.document_annotations definition

-- Drop table

-- DROP TABLE public.document_annotations;

CREATE TABLE public.document_annotations (
	id serial4 NOT NULL,
	document_id int4 NULL,
	"type" varchar(20) NOT NULL,
	page_num int4 NOT NULL,
	pos_x float8 DEFAULT 50 NOT NULL,
	pos_y float8 DEFAULT 50 NOT NULL,
	width float8 DEFAULT 20 NOT NULL,
	height float8 DEFAULT 5 NOT NULL,
	color varchar(10) DEFAULT '#000000'::character varying NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT document_annotations_pkey PRIMARY KEY (id),
	CONSTRAINT document_annotations_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE
);


-- public.document_metadata definition

-- Drop table

-- DROP TABLE public.document_metadata;

CREATE TABLE public.document_metadata (
	id serial4 NOT NULL,
	document_id int4 NULL,
	field_id int4 NULL,
	field_value text NULL,
	CONSTRAINT document_metadata_pkey PRIMARY KEY (id),
	CONSTRAINT document_metadata_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE,
	CONSTRAINT document_metadata_field_id_fkey FOREIGN KEY (field_id) REFERENCES public.template_fields(id) ON DELETE CASCADE
);


-- public.document_stamps definition

-- Drop table

-- DROP TABLE public.document_stamps;

CREATE TABLE public.document_stamps (
	id serial4 NOT NULL,
	document_id int4 NULL,
	stamp_id int4 NULL,
	page_num int4 NOT NULL,
	pos_x float8 DEFAULT 50 NOT NULL,
	pos_y float8 DEFAULT 50 NOT NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	CONSTRAINT document_stamps_pkey PRIMARY KEY (id),
	CONSTRAINT document_stamps_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE,
	CONSTRAINT document_stamps_stamp_id_fkey FOREIGN KEY (stamp_id) REFERENCES public.stamps(id) ON DELETE CASCADE
);


-- public.document_versions definition

-- Drop table

-- DROP TABLE public.document_versions;

CREATE TABLE public.document_versions (
	id serial4 NOT NULL,
	document_id int4 NULL,
	version_number int4 NULL,
	filename varchar(255) NOT NULL,
	action_type varchar(255) NULL,
	created_at timestamp DEFAULT CURRENT_TIMESTAMP NULL,
	created_by int4 NULL,
	CONSTRAINT document_versions_pkey PRIMARY KEY (id),
	CONSTRAINT document_versions_created_by_fkey FOREIGN KEY (created_by) REFERENCES public.users(id),
	CONSTRAINT document_versions_document_id_fkey FOREIGN KEY (document_id) REFERENCES public.documents(id) ON DELETE CASCADE
);

-- Insert default admin user (password: password)
INSERT INTO public.users (username, password_hash, "role") 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin') 
ON CONFLICT (username) DO NOTHING;

-- Insert the root folder (ID 0)
INSERT INTO public.folders (id, name, parent_id, created_by) VALUES (0, 'Root', NULL, 1) ON CONFLICT (id) DO NOTHING;

-- Create the effective permissions view
CREATE OR REPLACE VIEW public.vw_effective_permissions AS
SELECT 
    u.id AS user_id,
    f.id AS folder_id,
    bool_or(
        COALESCE(fp.right_view, false) OR COALESCE(fgp.right_view, false)
    ) AS right_view,
    bool_or(
        COALESCE(fp.right_add, false) OR COALESCE(fgp.right_add, false)
    ) AS right_add,
    bool_or(
        COALESCE(fp.right_modify, false) OR COALESCE(fgp.right_modify, false)
    ) AS right_modify,
    bool_or(
        COALESCE(fp.right_delete, false) OR COALESCE(fgp.right_delete, false)
    ) AS right_delete,
    bool_or(
        COALESCE(fp.right_see_through_redactions, false) OR COALESCE(fgp.right_see_through_redactions, false)
    ) AS right_see_through_redactions,
    bool_or(
        COALESCE(fp.right_manage_security, false) OR COALESCE(fgp.right_manage_security, false)
    ) AS right_manage_security,
    bool_or(
        (COALESCE(fp.right_view, false) AND fp.scope IN ('this_folder_subfolders_documents', 'this_folder_documents')) OR 
        (COALESCE(fgp.right_view, false) AND fgp.scope IN ('this_folder_subfolders_documents', 'this_folder_documents'))
    ) AS right_view_documents
FROM public.users u
CROSS JOIN public.folders f
LEFT JOIN public.folder_permissions fp ON fp.user_id = u.id AND fp.folder_id = f.id
LEFT JOIN public.user_groups ug ON ug.user_id = u.id
LEFT JOIN public.folder_group_permissions fgp ON fgp.group_id = ug.group_id AND fgp.folder_id = f.id
GROUP BY u.id, f.id;