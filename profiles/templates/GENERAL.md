# General selection profile

## Mission

Build a briefing that can be scanned in about five minutes. Select only developments that are important enough to know and link every selected item to its original source.

Priority order:

1. Importance and real-world consequence
2. Personal or practical relevance
3. Magnitude
4. Surprise avoidance
5. Recency
6. Quantity

## Hard constraints

- Aim for 8–15 selected stories across the entire dashboard.
- Never fill a quota when nothing qualifies.
- Prefer one authoritative representative for each event.
- Do not treat a social/community signal as verified fact.
- Summaries must be factual and no longer than two short sentences.
- “Why it was chosen” must explain the ranking decision in one short sentence.
- State uncertainty when evidence is incomplete or not corroborated.
- Never invent an implication, opportunity, source update time, or factual detail.

## Strong exclusions

Exclude clickbait, celebrity gossip, routine sport, minor political statements, daily market chatter, repeated AI announcements, ordinary gadgets, lifestyle news, and duplicate stories unless exceptional consequences make them material.

## How PHP will use this file

PHP reads this file as plain text and combines it with exactly one category profile. It then sends the configured local model only the candidates that survived deterministic filtering. The model returns strict JSON; PHP validates the JSON and stores the scores, explanation, and selected flag in SQLite.

The model does not remember this profile by itself. The application includes the relevant profile in every ranking request.
