<?php

// Return one preference score per cuisine for the current account.
function getRecommendationScores($pdo, $userId)
{
    // Guests have no personal interaction history to score.
    if ($userId < 1) {
        return array();
    }

    // Combine the four interaction signals and total their points by cuisine.
    // Likes, saves, and views exclude the user's own posts.
    // View/search counts remain cumulative; only recently active records qualify.
    $sql = "
        SELECT
            preferences.cuisine,
            SUM(preferences.points) AS score

        FROM (
            /* Current likes: 3 points per post. */
            SELECT
                p.cuisine,
                COUNT(*) * 3 AS points

            FROM nomnom_post_likes likes
            INNER JOIN nomnom_posts p
                ON p.id = likes.post_id

            WHERE likes.user_id = ?
              AND p.author_id <> ?

            GROUP BY p.cuisine

            UNION ALL

            /* Current saves: 5 points per post. */
            SELECT
                p.cuisine,
                COUNT(*) * 5 AS points

            FROM nomnom_saved_posts saves
            INNER JOIN nomnom_posts p
                ON p.id = saves.post_id

            WHERE saves.user_id = ?
              AND p.author_id <> ?

            GROUP BY p.cuisine

            UNION ALL

            /* Views: 1 point per recorded post-view day, capped at 5 per cuisine.
               Include records last viewed within seven days. */
            SELECT
                p.cuisine,
                LEAST(5, SUM(views.view_days)) AS points

            FROM nomnom_post_views views
            INNER JOIN nomnom_posts p
                ON p.id = views.post_id

            WHERE views.user_id = ?
              AND p.author_id <> ?
              AND views.last_viewed_at >=
                  DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)

            GROUP BY p.cuisine

            UNION ALL

            /* Searches: 2 points per recorded search day, capped at 6 per cuisine.
               Match keywords against post details to identify cuisines. */
            SELECT
                matches.cuisine,
                LEAST(3, SUM(matches.search_days)) * 2 AS points

            FROM (
                /* Count a keyword once per cuisine, even if many posts match. */
                SELECT DISTINCT
                    history.search_query,
                    history.search_days,
                    p.cuisine

                FROM nomnom_search_history history

                INNER JOIN nomnom_posts p
                    ON LOCATE(
                        history.search_query,
                        LOWER(
                            CONCAT_WS(
                                ' ',
                                p.title,
                                p.description,
                                p.restaurant_name,
                                p.location,
                                p.cuisine
                            )
                        )
                    ) > 0

                WHERE history.user_id = ?
                  AND history.last_searched_at >=
                      DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)
            ) AS matches

            GROUP BY matches.cuisine
        ) AS preferences

        GROUP BY preferences.cuisine
    ";

    // Bind the account ID to the placeholders in the four queries.
    $statement = $pdo->prepare($sql);

    $statement->execute(array(
        $userId,
        $userId,
        $userId,
        $userId,
        $userId,
        $userId,
        $userId,
    ));

    // Build a cuisine-to-score lookup for feed.php, for example Korean => 23.
    $scores = array();

    foreach ($statement->fetchAll() as $row) {
        $scores[$row['cuisine']] = (int) $row['score'];
    }

    return $scores;
}
