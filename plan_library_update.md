# Implementation Plan: Streamlined Book Marketplace

## Objective
Simplify the book listing process by removing image uploads and fully implement the "Buy" and "Sell" transaction flow for used books.

## 1. Simplify "List a Book"
- **Remove Image Upload**: Remove the file input field from the "List a Book" form.
- **Default Cover**: Use a randomly assigned color or generic book icon pattern for books since custom images are removed.
- **Explicit Options**: Ensure the form clearly asks "I want to: Sell / Lend".

## 2. Buying & Selling Flow
- **Display Availability**: 
    - Books listed as 'Sell' will have a **"Buy for $X"** button.
    - Books listed as 'Borrow' will have a **"Request / Borrow"** button.
- **Backend Transaction Logic**:
    - `POST action=buy_book`:
        - Updates book status from `Available` -> `Sold`.
        - Updates `user_id` (owner) to the *buyer*.
        - Or: Keeps history? For simplicity, we'll transfer ownership in the `books` table so it shows up in the buyer's "My Tracker" as a new book (maybe with status 'wishlist' or 'reading' initially).
        - Ideally, we should keep a history, but moving ownership is easiest for "Used Book Sale".
    - `POST action=borrow_book`:
        - Updates status to `Borrowed`.
        - Tracks who borrowed it.

## 3. UI Updates
- **Marketplace Grid**:
    - Remove image `<img>` tags. Replace with a CSS-styled book cover div.
    - Add the interactive "Buy" / "Borrow" buttons.
- **Availability Filter**:
    - Ensure only 'Available' books are shown in the main marketplace text.

## 4. Execution Steps
1.  **Modify `reading.php` Form**: Remove file input and update PHP handler.
2.  **Modify `reading.php` Display**: Update the card design to valid CSS-only covers.
3.  **Implement `buy_book` Action**: Add PHP logic to handle ownership transfer.
