=== Mind - AI Page Builder ===
Contributors:      nko
Tags:              ai, gpt, ai page builder, ai editor, copilot
Requires at least: 6.2
Tested up to:      6.7
Requires PHP:      7.2
Stable tag:        0.2.0
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

AI page builder for WordPress which let's you build sections, redesign existing blocks, etc...

== Description ==

Mind is a WordPress plugin that transforms your page building experience. Powered by AI technology, it helps you create and modify entire page sections, layouts, and content directly in the WordPress editor. With support for both Anthropic and OpenAI AI models, Mind seamlessly integrates with the WordPress block editor to enhance your page building workflow.

=== 🚀 Community-Driven Development ===

This plugin was created as an experiment in the hope that the community will influence the course of its development. Any wishes, feature requests, or bug reports are welcome and will be the motivation for developing the functionality of the plugin in [GitHub Discussions](https://github.com/nk-crew/mind/discussions).

=== ✍️ Writing Assistance ===

Mind provides various tools to help with writing content. You can use it to write an entire post, create a catchy post title, or draft a comprehensive post outline. Whether you need assistance in brainstorming ideas or structuring your content, Mind is there to support you.

- Write a post about specific topic
- Write a post title about specific topic
- Write a post outline about specific topic

=== 📝 Writing Language Improvement ===

With Mind, you can improve the language of your writing. It helps you fix spelling and grammar errors, ensuring that your content is polished and professional. Additionally, Mind can make your writing shorter or longer, based on your preferences.

- Improve writing language
- Fix spelling & grammar
- Make shorter
- Make longer

=== ✂️ Summarization and Paraphrasing ===

Mind offers the ability to summarize lengthy content, making it easier for readers to grasp the main points. It can also help you paraphrase sentences or paragraphs, ensuring that your content is unique and engaging.

- Summarize
- Paraphrase

=== 🤗 Tone Adjustment ===

Mind allows you to adjust the tone of your writing to suit your desired style.

- Professional
- Friendly
- Straightforward
- Educational
- Confident
- Witty
- Heartfelt

=== 🌐 Translation ===

Mind supports translation into multiple languages. It enables you to reach a wider audience by translating your content accurately and efficiently. Supported languages:

- Chinese
- Dutch
- English
- Filipino
- French
- German
- Indonesian
- Italian
- Japanese
- Korean
- Portuguese
- Russian
- Spanish

=== ⚙️ Features ===

There are a couple of places, which implemented in Mind to help writing content:

- Mind Popup - open the popup to write a blog post content or send a specific request to AI
- Paragraph Enhancer - select existing paragraphs and enhance it using Toolbar Mind button
- Press `space` in the empty paragraph to instantly open the Mind Popup and make a request

=== Other ===

With its comprehensive set of features, Mind empowers content editors to write high-quality and engaging posts, while saving time and effort in the content creation process.

p.s. this plugin description is created using Mind and OpenAI API.

=== OpenAI ===

The AI Mind plugin utilizes [OpenAI](https://platform.openai.com/) API without collecting any personal information. Data transmitted to OpenAI servers includes post content and specified context.

For the AI Mind plugin to function correctly, you need an API key from OpenAI. Sign up at <https://platform.openai.com/account/api-keys> to obtain the key.

Please make sure to review their [Privacy Policy](https://openai.com/policies/privacy-policy), as well as their [Terms of Use](https://openai.com/policies/terms-of-use) for more information.


== Installation ==

= Automatic installation =

Install the Mind either via the WordPress plugin directory or by uploading the files to your server at `wp-content/plugins`.

= Usage =

To start using the Mind features you have to insert your OpenAI API key in the plugin settings.

- Create OpenAI API key <https://platform.openai.com/account/api-keys>
- Insert this key in the plugin settings (Admin Menu → Mind → Settings)
- Open any post or page in WordPress editor (Gutenberg) and you can see the new button in the editor and paragraph toolbars

== Frequently Asked Questions ==

= Website and Documentation =

There is no documentation available yet, but in the future it will be placed here - <https://www.wp-mind.com/>

= Supported page builders =

Mind is developed for the WordPress page builder - Gutenberg. Currently we don't have support for 3rd-party builders.

== Screenshots ==

1. Ask AI to enhance section with blocks
2. Result of enhanced section
3. Paragraph toolbar button

== Changelog ==

= 0.2.0 - Dec 9, 2024 =

- added stream AI response for better experience
- fixed JS error when inserting response to editor
- changed model to gpt-4o-mini

= 0.1.3 - Nov 28, 2024 =

- fixed the Open Mind button display issue in the WordPress 6.7 posts toolbar

= 0.1.2 - Nov 28, 2024 =

- added translation files
- check compatibility with WordPress 6.7

= 0.1.1 - 21 Jan, 2024 =

- fixed displaying OpenAI errors

= 0.1.0 - 14 Nov, 2023 =

- Release
