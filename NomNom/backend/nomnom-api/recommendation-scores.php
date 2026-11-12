<?php

function getRecommendationScores($pdo, $userId)
{
    if ($userId < 1) {
        return array();
    }

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

            /* Views: maximum 5 points per cuisine. */
            SELECT
                p.cuisine,
                LEAST(5, SUM(views.view_days)) AS points

            FROM nomnom_post_views views
            INNER JOIN nomnom_posts p
                ON p.id = views.post_id

            WHERE views.user_id = ?
              AND p.author_id <> ?
              AND views.last_viewed_at >=
                  DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)

            GROUP BY p.cuisine

            UNION ALL

            /* Searches: maximum 6 points per cuisine. */
            SELECT
                matches.cuisine,
                LEAST(3, SUM(matches.search_days)) * 2 AS points

            FROM (
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
                      DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY)
            ) AS matches

            GROUP BY matches.cuisine
        ) AS preferences

        GROUP BY preferences.cuisine
    ";

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

    $scores = array();

    foreach ($statement->fetchAll() as $row) {
        $scores[$row['cuisine']] = (int) $row['score'];
    }

    return $scores;
}