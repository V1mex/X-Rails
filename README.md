# X-Rails

Bachelor's diploma project. A web application that automates the 
commercial cycle of rail freight transportation for logistics companies.

## Features
- Contract management: creation, validation, auto-save to database
- Multi-role access: Manager (contracts, shipments) and 
  Administrator (tariffs, analytics)
- Automatic freight cost calculation based on configurable tariffs
- Shipment tracking and arrival registration
- Statistics and reporting via optimized SQL queries
- Adaptive UI built with CSS Grid/Flexbox

## Tech Stack
PHP 8.1 · MySQL · Apache · HTML/CSS · Page Controller pattern

## Architecture
Normalized relational database with JOINs, GROUP BY, and HAVING 
clauses for statistical queries. Functional programming principles 
applied in PHP: pure functions and immutable data structures for 
reliable business logic.
