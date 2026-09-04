<?php
declare(strict_types=1);

function ofx_pages_howto(): void
{
    ofx_render('pages/howto', ['title' => 'How To']);
}

function ofx_pages_about_openframeworks(): void
{
    ofx_render('pages/about-openframeworks', ['title' => 'About openFrameworks']);
}

// GET /sitemap - human-readable index of every page/section on the
// site, each with a one-line description of what it's for. The
// machine-readable /sitemap.xml and /sitemap.json cover URLs for every
// individual addon/category/contributor; this page is the "what is
// there to browse" overview for a person, linking out to those two.
function ofx_pages_sitemap(): void
{
    ofx_render('pages/sitemap', ['title' => 'Sitemap']);
}
