# Writes the theme's block templates. Run: python dev/build_templates.py
# Kept as a generator so header/main/footer framing stays identical across templates.
from pathlib import Path

T = Path(__file__).resolve().parent.parent / "wp-content/themes/alderframe/templates"
H = '<!-- wp:template-part {"slug":"header","tagName":"header","className":"af-site-header"} /-->'
F = '<!-- wp:template-part {"slug":"footer","tagName":"footer","className":"af-site-footer"} /-->'


def main(cls, inner):
    return (f'{H}\n<!-- wp:group {{"tagName":"main","className":"af-main {cls}"}} -->\n'
            f'<main id="main" class="wp-block-group af-main {cls}">\n{inner}\n</main>\n<!-- /wp:group -->\n{F}\n')


def plain_hero(title_block, intro_block="", extra=""):
    return ('<!-- wp:group {"className":"af-hero af-hero--page af-hero--plain' + extra + '"} -->\n'
            '<div class="wp-block-group af-hero af-hero--page af-hero--plain' + extra + '"><div class="af-hero__text">\n'
            f'{title_block}\n{intro_block}\n</div></div>\n<!-- /wp:group -->')


def h1(text):
    return f'<!-- wp:heading {{"level":1,"className":"af-hero__title"}} --><h1 class="wp-block-heading af-hero__title">{text}</h1><!-- /wp:heading -->'


def intro(text):
    return f'<!-- wp:paragraph {{"className":"af-hero__intro"}} --><p class="af-hero__intro">{text}</p><!-- /wp:paragraph -->'


POSTCARD = '''<!-- wp:post-template {"className":"af-posts"} -->
<!-- wp:group {"className":"af-post-card"} --><div class="wp-block-group af-post-card">
<!-- wp:post-featured-image {"isLink":true,"sizeSlug":"medium_large","className":"af-post-card__media"} /-->
<!-- wp:group {"className":"af-post-card__body"} --><div class="wp-block-group af-post-card__body">
<!-- wp:post-terms {"term":"category","className":"af-post-card__cat"} /-->
<!-- wp:post-title {"level":2,"isLink":true,"className":"af-post-card__title"} /-->
<!-- wp:post-excerpt {"className":"af-post-card__excerpt"} /-->
<!-- wp:group {"className":"af-byline"} --><div class="wp-block-group af-byline"><!-- wp:post-author-name /--><!-- wp:post-date /--></div><!-- /wp:group -->
</div><!-- /wp:group -->
</div><!-- /wp:group -->
<!-- /wp:post-template -->'''

CONTENT = '<!-- wp:post-content {"className":"af-content"} /-->'
GARDEN_LIST = '<!-- wp:alderframe/garden-filter /-->\n<!-- wp:alderframe/garden-archive /-->\n<!-- wp:alderframe/visit-cta /-->'

templates = {
    "index": main("af-page", CONTENT),
    "page": main("af-page", CONTENT),
    "front-page": main("af-page af-page--home", CONTENT),
    "page-campaign": main("af-page af-page--campaign", CONTENT),
    "archive-af_garden": main("af-archive", plain_hero(h1("Completed gardens"), intro("Private gardens in Toronto and Oakville, grouped by the site problem each one had to solve. We publish a garden only with the owners' permission, and we name the neighbourhood, never the address.")) + "\n" + GARDEN_LIST),
    "taxonomy-site_condition": main("af-archive", plain_hero('<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"af-hero__title"} /-->', '<!-- wp:term-description {"className":"af-hero__intro"} /-->') + "\n" + GARDEN_LIST),
    "single-af_garden": main("af-single af-single--garden", '''<!-- wp:group {"className":"af-hero af-hero--page af-hero--garden"} -->
<div class="wp-block-group af-hero af-hero--page af-hero--garden"><div class="af-hero__text">
<!-- wp:post-title {"level":1,"className":"af-hero__title"} /-->
<!-- wp:alderframe/garden-facts /-->
</div>
<!-- wp:post-featured-image {"className":"af-hero__media","sizeSlug":"full"} /-->
</div>
<!-- /wp:group -->
<!-- wp:alderframe/garden-narrative /-->
<!-- wp:alderframe/garden-gallery /-->
<!-- wp:alderframe/site-model {"heading":"The terraces in 3D","text":"A computer model of the three terraces and the rill, built from the project description rather than from site photographs."} /-->
<!-- wp:alderframe/owner-quote /-->
<!-- wp:alderframe/related-gardens {"count":3} /-->
<!-- wp:alderframe/visit-cta /-->'''),
    "single-af_service": main("af-single af-single--service", plain_hero('<!-- wp:post-title {"level":1,"className":"af-hero__title"} /-->') + '''
<!-- wp:alderframe/service-facts /-->
<!-- wp:alderframe/process-timeline /-->
<!-- wp:alderframe/garden-grid {"heading":"Gardens shaped by this service","count":3} /-->
<!-- wp:alderframe/faq-list {"stage":"design","heading":"Design stage questions"} /-->
<!-- wp:alderframe/visit-cta /-->'''),
    "home": main("af-journal", plain_hero(h1("Studio journal")) + '''
<!-- wp:categories {"className":"af-cats","showPostCounts":false} /-->
<!-- wp:query {"queryId":1,"query":{"inherit":true,"perPage":10},"className":"af-query"} -->
<div class="wp-block-query af-query">
''' + POSTCARD + '''
<!-- wp:query-pagination {"className":"af-pager"} --><!-- wp:query-pagination-previous {"label":"Newer"} /--><!-- wp:query-pagination-next {"label":"Older"} /--><!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
<!-- wp:alderframe/visit-cta /-->'''),
    "category": main("af-journal", plain_hero('<!-- wp:query-title {"type":"archive","showPrefix":false,"className":"af-hero__title"} /-->', '<!-- wp:term-description {"className":"af-hero__intro"} /-->') + '''
<!-- wp:categories {"className":"af-cats","showPostCounts":false} /-->
<!-- wp:query {"queryId":1,"query":{"inherit":true,"perPage":10},"className":"af-query"} -->
<div class="wp-block-query af-query">
''' + POSTCARD + '''
</div>
<!-- /wp:query -->
<!-- wp:alderframe/visit-cta /-->'''),
    "single": main("af-single af-single--post", '''<!-- wp:group {"className":"af-article"} -->
<div class="wp-block-group af-article">
<!-- wp:post-terms {"term":"category","className":"af-article__cat"} /-->
<!-- wp:post-title {"level":1,"className":"af-article__title"} /-->
<!-- wp:post-excerpt {"className":"af-article__dek"} /-->
<!-- wp:group {"className":"af-byline"} --><div class="wp-block-group af-byline"><!-- wp:post-author-name /--><!-- wp:post-date /--></div><!-- /wp:group -->
</div>
<!-- /wp:group -->
<!-- wp:post-featured-image {"className":"af-article__media","sizeSlug":"full"} /-->
<!-- wp:post-content {"className":"af-prose af-article__body","layout":{"type":"constrained"}} /-->
<!-- wp:alderframe/related-garden /-->
<!-- wp:group {"className":"af-postnav"} --><div class="wp-block-group af-postnav"><!-- wp:post-navigation-link {"type":"previous","label":"Previous article","showTitle":true} /--><!-- wp:post-navigation-link {"label":"Next article","showTitle":true} /--></div><!-- /wp:group -->
<!-- wp:alderframe/visit-cta /-->'''),
    "search": main("af-search-page", plain_hero('<!-- wp:query-title {"type":"search","className":"af-hero__title"} /-->') + '''
<!-- wp:alderframe/search-form /-->
<!-- wp:query {"queryId":2,"query":{"inherit":true,"perPage":10},"className":"af-query af-query--search"} -->
<div class="wp-block-query af-query af-query--search">
<!-- wp:post-template {"className":"af-results"} -->
<!-- wp:group {"className":"af-result"} --><div class="wp-block-group af-result">
<!-- wp:post-title {"level":2,"isLink":true,"className":"af-result__title"} /-->
<!-- wp:post-excerpt {"className":"af-result__excerpt"} /-->
</div><!-- /wp:group -->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:group {"className":"af-empty"} --><div class="wp-block-group af-empty">
<!-- wp:paragraph --><p>Nothing matches that search. Try a garden name, a service, or a site condition such as ravine or clay.</p><!-- /wp:paragraph -->
<!-- wp:paragraph --><p><a class="af-link" href="/gardens/">See completed gardens</a> <a class="af-link" href="/design-services/">Compare services</a></p><!-- /wp:paragraph -->
</div><!-- /wp:group -->
<!-- /wp:query-no-results -->
</div>
<!-- /wp:query -->'''),
    "404": main("af-404", plain_hero(h1("We can't find that page"), intro("It may have moved, or a garden may have been taken down at the owners' request.") + '''
<!-- wp:paragraph {"className":"af-hero__actions"} --><p class="af-hero__actions"><a class="af-btn af-btn--action" href="/gardens/">See completed gardens</a> <a class="af-link af-link--quiet" href="/design-services/">Compare services</a></p><!-- /wp:paragraph -->''') + '\n<!-- wp:alderframe/search-form /-->'),
}

T.mkdir(parents=True, exist_ok=True)
for name, html in templates.items():
    (T / f"{name}.html").write_text(html, encoding="utf-8")
parts = T.parent / "parts"
parts.mkdir(exist_ok=True)
(parts / "header.html").write_text("<!-- wp:alderframe/site-header /-->\n", encoding="utf-8")
(parts / "footer.html").write_text("<!-- wp:alderframe/site-footer /-->\n", encoding="utf-8")
print("wrote", len(templates), "templates")
