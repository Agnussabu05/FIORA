# Implementation Plan: Fiora Book Exchange (The Library) 📚

## Objective
Create a marketplace module within Fiora where users can **Buy**, **Sell**, and **Borrow** used books. The system will allow users to browse books by category, view availability, and manage their own book listings.

---

## 1. Database Schema Design (MySQL)

We need new tables to manage books and transactions.

### A. `books` Table
Stores details about the books available.
- `id` (INT, PK, Auto-increment)
- `user_id` (INT, FK -> users.id) - The owner/seller
- `title` (VARCHAR)
- `author` (VARCHAR)
- `category` (VARCHAR) - e.g., 'Fiction', 'Science', 'Textbook', 'Self-Help'
- `description` (TEXT)
- `condition` (ENUM: 'New', 'Like New', 'Good', 'Fair')
- `type` (ENUM: 'Sell', 'Borrow', 'Both')
- `price` (DECIMAL, 10,2) - 0.00 if free/borrow only
- `image_path` (VARCHAR)
- `status` (ENUM: 'Available', 'Sold', 'Borrowed', 'Reserved')
- `created_at` (TIMESTAMP)

### B. `book_transactions` Table
Tracks who bought or borrowed what.
- `id` (INT, PK)
- `book_id` (INT, FK -> books.id)
- `buyer_id` (INT, FK -> users.id) - The person receiving the book
- `seller_id` (INT, FK -> users.id)
- `transaction_type` (ENUM: 'Purchase', 'Loan')
- `status` (ENUM: 'Pending', 'Completed', 'Returned', 'Overdue')
- `due_date` (DATE) - NULL if purchase, required if Loan
- `transaction_date` (TIMESTAMP)

---

## 2. Frontend Interface (`books.php`)

A new main page accessible from the sidebar.

### A. Main Dashboard (The Marketplace)
- **Top Section**: Search bar + Category Pills (e.g., "All", "Textbooks", "Fiction").
- **Grid View**: Cards displaying:
  - Book Cover Image
  - Title & Author
  - Price (or "Free to Borrow")
  - Owner Username
  - "Buy" or "Borrow" Action Buttons
- **Sidebar Box**: "My Listings" summary and "Add New Book" button.

### B. "Add a Book" Modal/Page
A clean form to list a book:
- Upload Image (Drag & Drop)
- Title / Author / Auto-fill via Google Books API (Optional future polish)
- Select Category
- Set Price (or mark as Borrow only)

### C. "My Library" Tab
- **My Listings**: Books I am selling/lending.
- **My Shelf**: Books I have bought or explicitly borrowed from others.

---

## 3. Backend Logic

### A. `api/books.php`
- **POST `add_book`**: Handle image upload and insert into DB.
- **GET `get_books`**: Fetch books with filtering (Category, Status=Available).
- **POST `request_book`**:
  - If "Buy": Change status to 'Sold' (Simulated purchase) or 'Pending' if approval needed.
  - If "Borrow": Change status to 'Borrowed'.

### B. Integration
- Add "Library" icon to `includes/sidebar.php`.
- Link to existing User Balance (if `finance.php` exists, we can deduce virtual money!).

---

## 4. Implementation Steps

1.  **Database Migration**: Run SQL to create tables.
2.  **Create Page Structure**: Create `books.php` with the standard Fiora layout/sidebar.
3.  **Build The "Add Book" Form**: Focus on the UI and image upload first.
4.  **Display Logic**: Create the grid view to fetch and display books from the DB.
5.  **Transaction Logic**: Implement the "Buy" button logic to update database statuses.
6.  **Categories**: Add category filtering to the main query.

---

## 5. Future Polish (AI Features) 
*Since we have Gemini integrated:*
- **AI Book Matcher**: "Based on your mood (from mood.php), you should read *The Alchemist*."
- **Smart Summaries**: Auto-generate a description for the book listing using the title/author.
