Round 1 (today's session): Copy the default single-product.php verbatim → confirm it renders identical to current → push to staging → verify add-to-cart, variations, subscriptions, checkout all still work end-to-end. This proves the pipeline and gives you a known-good baseline.

Round 2: Add the slug-based conditional for the 2 weight loss products, but initially just change low-risk things — reorder sections, add custom copy blocks, swap styling. Don't touch the variations form yet.

Round 3: Once confident, rebuild the variant selector UI, carefully replicating the events the snippets and plugins expect.