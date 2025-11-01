/**
 * Disallow raw <a> elements rendered inside <nav>.
 * Forces teams to use <PrimaryNavLink> or explicitly opt out.
 */

module.exports = {
  meta: {
    type: 'problem',
    docs: {
      description: 'Use PrimaryNavLink inside navigation regions.',
    },
    schema: [],
    messages: {
      usePrimaryNavLink: 'Replace raw <a> inside <nav> with <PrimaryNavLink> or add data-allow-nav-anchor="true".',
    },
  },
  create(context) {
    return {
      JSXOpeningElement(node) {
        if (!node.name || node.name.type !== 'JSXIdentifier' || node.name.name !== 'a') {
          return;
        }

        const hasOverride = node.attributes?.some(attr => {
          return attr.type === 'JSXAttribute'
            && attr.name
            && attr.name.name === 'data-allow-nav-anchor';
        });
        if (hasOverride) {
          return;
        }

        const ancestors = context.getAncestors();
        const insideNav = ancestors.some(ancestor => {
          if (ancestor.type !== 'JSXElement') {
            return false;
          }
          const opening = ancestor.openingElement;
          return (
            opening
            && opening.name
            && opening.name.type === 'JSXIdentifier'
            && opening.name.name === 'nav'
          );
        });

        if (insideNav) {
          context.report({
            node,
            messageId: 'usePrimaryNavLink',
          });
        }
      },
    };
  },
};
