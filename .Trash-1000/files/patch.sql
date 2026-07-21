-- Insert the root folder (ID 0)
INSERT INTO public.folders (id, name, parent_id, created_by) VALUES (0, 'Root', NULL, 1) ON CONFLICT (id) DO NOTHING;

-- Recreate the missing view
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
