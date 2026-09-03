/** @type {import('tailwindcss').Config} */
module.exports = {
    // Every Webkul package's Blade/JS, not just Admin's own — a package with
    // no views of its own simply contributes no matches. Without this, a
    // class that's unique to a satellite package's markup (an arbitrary
    // value like z-[9999], an opacity modifier like bg-black/50, anything
    // Admin's own views don't happen to also use) silently never gets
    // generated, and looks "broken" only in the browser, never in the code.
    content: [
        "./src/Resources/**/*.blade.php",
        "./src/Resources/**/*.js",
        "../*/src/Resources/**/*.blade.php",
        "../*/src/Resources/**/*.js",
    ],

    theme: {
        container: {
            center: true,

            screens: {
                "4xl": "1920px",
            },

            padding: {
                DEFAULT: "16px",
            },
        },

        screens: {
            sm: "525px",
            md: "768px",
            lg: "1024px",
            xl: "1240px",
            "2xl": "1440px",
            "3xl": "1680px",
            "4xl": "1920px",
        },

        extend: {
            colors: {
                brandColor: "var(--brand-color)",
            },

            fontFamily: {
                inter: ['Inter'],
                icon: ['icomoon']
            }
        },
    },
    
    darkMode: 'class',

    plugins: [],

    safelist: [
        {
            pattern: /icon-/,
        }
    ]
};