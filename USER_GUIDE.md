# StrataDMS User Guide

Welcome to the official StrataDMS User Guide. This document provides step-by-step instructions on how to use the core features of the Document Management System, from basic file organization to advanced PDF manipulation and security management.

---

## 1. Getting Started

### Logging In
1. Navigate to the StrataDMS URL provided by your administrator.
2. Enter your credentials on the login screen.
3. If this is your **first time logging in** as the default Admin:
   - Go to **Settings** (gear icon in the sidebar).
   - Update your password immediately.

### Customizing the Brand Logo
*(Admin Only)*
1. Navigate to the **Settings** panel.
2. Click **Choose File** under the Logo Upload section and select your company's image (PNG or JPG).
3. Click **Upload New Logo**. The interface will instantly update to reflect your branding.

---

## 2. Folder & Document Management

StrataDMS operates on a hierarchical folder system, similar to Windows Explorer or macOS Finder.

### Creating and Managing Folders
- **Create:** Click the **"New Folder"** button in the top toolbar. Enter a name and save.
- **Rename:** Right-click (or use the "..." menu) on a folder and select **Rename**.
- **Move:** You can drag and drop folders into other folders, or right-click and select **Move** to choose a destination from the tree view.

### Uploading Documents
1. Navigate into the desired folder.
2. Click **Upload File** in the top toolbar, or simply **drag and drop** a PDF file from your computer directly into the browser window.
3. The system will automatically generate a high-quality thumbnail for previewing.

### The Recycle Bin
When you delete a file or folder, it is not permanently destroyed immediately.
1. Click **Recycle Bin** in the left sidebar to view deleted items.
2. **Restore:** Right-click an item and select "Restore" to move it back to its original location.
3. **Empty:** Click "Empty Recycle Bin" to permanently destroy all contents. *(Warning: This action cannot be undone).*

---

## 3. Advanced PDF Tools

StrataDMS includes a powerful built-in PDF Workspace for manipulating documents without downloading them to your computer. Double Click on any document thumbnail to open the Workspace.

### Page Manipulation
- **Reorder Pages:** In the Workspace, click and drag the page thumbnails on the left side to reorder them. Click "Save New Order" when finished.
- **Delete Pages:** Hover over a page thumbnail and click the red trash icon to instantly remove it from the document.

### Merging Documents
1. In the main dashboard grid, select multiple PDFs by checking the boxes in their top-left corners.
2. A new "Bulk Actions" menu will appear at the top of the screen.
3. Select **Merge Selected PDFs**.
4. Provide a name for the new document. The system will combine them in the order they were selected.

### Watermarking
1. Open a document in the Workspace and click the **Watermark** tab on the right.
2. **Text Watermarks:** Type your text, select a position (e.g., Center, Top Right), and adjust the X/Y offsets for fine-grained placement.
3. **Image Watermarks:** Upload a transparent PNG to stamp over the document.
4. Click **Apply Watermark** to permanently stamp the document.

---

## 4. Document Versioning & Locking

Enterprise environments require strict control over document edits. StrataDMS handles this automatically to ensure that no two users can modify a document at the same time.

### Automatic File Locking
Whenever you double-click a document to open it in the Workspace, the system automatically "locks" the file.
1. The document will display a "Lock" icon in the dashboard for all other users.
2. No other user can edit this document's pages, metadata, or watermarks while you are working on it.
3. Once you close the Workspace or finish your edits, the lock is automatically released.

### Version History
Every time a document is modified (e.g., pages are deleted, merged, or watermarked), the system records the change.
1. Open a document in the Workspace and click the **Version History** tab.
2. You will see a timeline of every modification event.
3. Click **Download** next to any historical entry to retrieve the exact state of the PDF at that point in time.

---

## 5. Permissions & Security

StrataDMS features granular Role-Based Access Control (RBAC).

### User Roles
- **Admin:** Has global access to all settings, users, and documents, bypassing all folder-level restrictions.
- **User:** Can only see folders and documents they have explicitly been granted access to.

### Managing Folder Permissions
*(Admin or Folder Owners Only)*
1. Right-click a folder and select **Permissions**.
2. **Add Users/Groups:** Search for a user or group to add them to the access list.
3. **Assign Rights:** Check the boxes to grant specific abilities:
   - *View:* Can see the folder and read documents inside.
   - *Add:* Can upload new documents and create subfolders.
   - *Modify:* Can rename files, check-out documents, and alter pages.
   - *Delete:* Can move items to the Recycle Bin.
   - *Manage Security:* Can alter this permissions menu.
4. **Inheritance:** By default, permissions assigned to a folder automatically cascade down to all subfolders and documents inside it.

---

## 6. Search & Retrieval

### Advanced Search
1. Use the search bar at the top of the screen to quickly find documents by Title.
2. For deep searching, click the **Filter** icon next to the search bar.
3. You can search against custom metadata fields (e.g., "Invoice Number", "Client Name") attached to specific Document Templates.
4. The search results view functions exactly like a normal folder: you can select, merge, and delete documents directly from the results page.
