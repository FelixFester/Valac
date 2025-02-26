        function clearCookies() {
            document.cookie.split(";").forEach(function(c) {
                document.cookie = c.trim().split("=")[0] + "=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/";
            });
            alert("Cookies cleared! Now you can update the page.");
        }