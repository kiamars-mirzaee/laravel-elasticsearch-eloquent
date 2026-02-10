# Contributing to Elasticsearch Eloquent

Thank you for considering contributing to this package! Here are some guidelines to help you get started.

## Development Setup

1. Fork the repository
2. Clone your fork:
   ```bash
   git clone https://github.com/kiamars-mirzaee/elasticsearch-eloquent.git
   cd elasticsearch-eloquent
   ```

3. Install dependencies:
   ```bash
   composer install
   ```

4. Run tests:
   ```bash
   composer test
   ```

## Making Changes

1. Create a new branch:
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. Make your changes and write tests

3. Ensure all tests pass:
   ```bash
   composer test
   ```

4. Commit your changes with descriptive messages:
   ```bash
   git commit -m "Add feature: description of feature"
   ```

5. Push to your fork:
   ```bash
   git push origin feature/your-feature-name
   ```

6. Open a Pull Request

## Coding Standards

- Follow PSR-12 coding standards
- Write meaningful variable and method names
- Add PHPDoc comments for public methods
- Keep methods focused and single-purpose
- Use type hints wherever possible (PHP 8.1+)

## Testing

- Write tests for new features
- Ensure existing tests still pass
- Aim for high code coverage
- Use descriptive test method names

## Pull Request Guidelines

- Provide a clear description of the changes
- Reference any related issues
- Include examples if adding new features
- Update documentation if needed
- Add entries to CHANGELOG.md

## Feature Requests

Feel free to open an issue to discuss new features before implementing them.

## Bug Reports

When reporting bugs, please include:
- PHP version
- Laravel version
- Elasticsearch version
- Steps to reproduce
- Expected behavior
- Actual behavior
- Code samples if applicable

## Code of Conduct

- Be respectful and inclusive
- Welcome newcomers
- Give constructive feedback
- Focus on what's best for the community

## Questions?

Feel free to open an issue for questions or reach out to the maintainers.

Thank you for contributing! 🎉
