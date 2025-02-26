lightweight flat-file PHP imageboard engine named after Clara Valac. Fork of Yotsuba (not this one that is very popular).

## Changes
- Mod panel now is Admin Panel
- In admin panel it's possible to delete post (it adds text like "post deleted by admin" to both title and description) or delete post completely. Unlike in Yotsuba, both buttons including deletion of images (didn't tested different type of media, but it should work too).
- Admin panel now should separate posts per pages, if there's many of them.
- Rewritten themes. Engine would come with over 10 .css themes named after many anime girls.
- Admin panel would allow to edit each post.
- Fixed bugs like when symbols ' was shown in post as & #39; (without blank space). In admin panel it still might appear, or not. But I'll solve it soon.
- Website name. In code was added title just as on usual big imageboards.
- Theme changer near website name. Parsing all existing .css files from folder, works for all pages except admin panel (yet).

## Reasons to use it
- It's super lightweight! All what it's using is SQLite database.
- It's modern and can be used on PHP8 and more newer versions.

---

**Soon would be available**
