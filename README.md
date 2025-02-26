Lightweight flat-file PHP imageboard engine named after Clara Valac. Fork of Yotsuba (not this one that is very popular).

## Differences from Yotsuba
- Mod panel now is Admin Panel.
- Ability to delete cookies from your website. Can be useful for certain websites with Cloudflare caching.
- In admin panel it's possible to delete post (it adds text like "post deleted by admin" to both title and description) or delete post completely. Unlike in Yotsuba, both buttons including deletion of images (didn't tested different type of media, but it should work too).
- Comes with ready-to-use .json config for creating PWA app out of your website.
- Almost fixed bug when error message about wrong captcha has been shown many times if captcha was failed many times. Soon would be solved at 100%.
- Rewritten themes. Engine would come with over 10 .css themes named after many anime girls.
- Fixed bugs like when symbols ' was shown in post as & #39; (without blank space). In admin panel it still might appear, or not. But I'll solve it soon.
- Website name. In code was added title just as on usual big imageboards.
- Theme changer near website name. Parsing all existing .css files from folder, works for all pages except admin panel (yet).

## Reasons to use it
- It's super lightweight! All what it's using is SQLite database.
- It's modern and can be used on PHP8 and more newer versions.

---

**Soon would be available. TBA.**
